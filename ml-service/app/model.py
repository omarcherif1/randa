"""Entraînement et prédiction — Random Forest global (un seul modèle pour
tous les produits, cf. décision produit : trop peu de mois par article pour
justifier un modèle séparé par produit).

Le modèle apprend sur le RÉSIDU autour d'une tendance linéaire par article
(voir features.py) — sinon un modèle à arbres plafonne ses prévisions au
lieu de prolonger une tendance en hausse/baisse.

Persistance : `models/forecast_model.joblib` (modèle + contexte
d'encodage/tendances) et `models/meta.json` (métriques + date
d'entraînement, lus par /status sans recharger le modèle).
"""

from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

import joblib
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, r2_score

from .features import (
    ContexteModele,
    apprendre_tendances,
    construire_table_entrainement,
    etat_initial_par_article,
    lignes_vers_dataframe,
    mois_suivant,
)

MODELS_DIR = Path(__file__).resolve().parent.parent / "models"
MODEL_PATH = MODELS_DIR / "forecast_model.joblib"
META_PATH = MODELS_DIR / "meta.json"

# Contraintes volontairement modestes (min_samples_leaf, max_depth) : avec
# environ un millier de lignes d'entraînement au total, un arbre profond
# mémorise le bruit au lieu d'apprendre un vrai signal.
_PARAMS = dict(n_estimators=300, max_depth=8, min_samples_leaf=3, random_state=42)


class ModeleNonEntraineError(Exception):
    pass


def _wape(y_true: np.ndarray, y_pred: np.ndarray) -> float | None:
    """WAPE (Weighted Absolute Percentage Error) = somme des erreurs
    absolues / somme des vraies valeurs — PAS la MAPE classique (moyenne des
    erreurs en % produit par produit). Choix délibéré : avec des produits
    dont la quantité commandée descend parfois à quelques kg, une MAPE
    classique explose (une erreur de 200 kg sur un produit à 10 kg = 2000 %)
    et noie la précision réelle du modèle sur les gros volumes, qui sont
    ceux qui comptent le plus pour la planification. WAPE pondère chaque
    produit par son propre volume — standard reconnu pour ce cas précis.
    """
    total = float(np.sum(y_true))
    if total <= 0:
        return None
    return float(np.sum(np.abs(y_true - y_pred)) / total)


def entrainer(lignes: list[dict]) -> dict:
    df = lignes_vers_dataframe(lignes)
    if df.empty:
        raise ValueError("Aucune donnée à entraîner.")

    contexte = ContexteModele(
        article=ContexteModele.apprendre_categorie(df["article"]),
        famille=ContexteModele.apprendre_categorie(df.get("famille", df["article"].map(lambda _: None))),
        societe=ContexteModele.apprendre_categorie(df.get("societe", df["article"].map(lambda _: None))),
        tendances=apprendre_tendances(df),
    )

    X, y, articles = construire_table_entrainement(df, contexte)

    # Validation honnête façon "walk-forward" (même esprit que le backtest
    # PHP remplacé) : le DERNIER mois observé de chaque article sert de test,
    # le modèle final est ensuite réentraîné sur TOUT (test inclus) pour la
    # mise en production — le score annoncé n'est jamais gonflé en ayant vu
    # la donnée qu'il évalue. Les métriques sont calculées sur la quantité
    # RECONSTITUÉE (résidu + tendance), pas sur le résidu seul — c'est la
    # quantité en kg qui doit être précise, pas une valeur intermédiaire.
    articles_arr = np.array(articles)
    est_dernier = np.zeros(len(articles), dtype=bool)
    for article in set(articles):
        idx = np.where(articles_arr == article)[0]
        est_dernier[idx[-1]] = True

    mae = wape = r2 = None
    if est_dernier.sum() >= 5 and (~est_dernier).sum() >= 5:
        modele_eval = RandomForestRegressor(**_PARAMS)
        modele_eval.fit(X[~est_dernier], y[~est_dernier])
        residu_pred = modele_eval.predict(X[est_dernier])

        t_col = X[est_dernier][:, 4]  # colonne "t" — voir FEATURE_COLUMNS
        tendance_test = np.array([
            contexte.tendance(article, int(t))
            for article, t in zip(articles_arr[est_dernier], t_col)
        ])
        y_pred_kg = np.maximum(0.0, residu_pred + tendance_test)
        y_test_kg = y[est_dernier] + tendance_test

        mae = round(float(mean_absolute_error(y_test_kg, y_pred_kg)), 1)
        wape_val = _wape(y_test_kg, y_pred_kg)
        wape = round(wape_val, 4) if wape_val is not None else None
        r2 = round(float(r2_score(y_test_kg, y_pred_kg)), 3) if len(set(y_test_kg)) > 1 else None

    modele_final = RandomForestRegressor(**_PARAMS)
    modele_final.fit(X, y)

    MODELS_DIR.mkdir(parents=True, exist_ok=True)
    joblib.dump({"modele": modele_final, "contexte": contexte}, MODEL_PATH)

    meta = {
        "entraine_le": datetime.now(timezone.utc).isoformat(),
        "n_echantillons": int(len(X)),
        "n_produits": int(len(set(articles))),
        "mae_kg": mae,
        "wape": wape,
        "r2": r2,
    }
    META_PATH.write_text(json.dumps(meta, ensure_ascii=False, indent=2), encoding="utf-8")

    return meta


def modele_entraine() -> bool:
    return MODEL_PATH.exists() and META_PATH.exists()


def lire_statut() -> dict:
    if not modele_entraine():
        return {"entraine": False}
    meta = json.loads(META_PATH.read_text(encoding="utf-8"))
    return {"entraine": True, **meta}


def _charger():
    if not MODEL_PATH.exists():
        raise ModeleNonEntraineError(
            "Aucun modèle entraîné pour l'instant — utilisez /train d'abord."
        )
    contenu = joblib.load(MODEL_PATH)
    return contenu["modele"], contenu["contexte"]


def predire(lignes: list[dict], mois_fin: str) -> dict:
    modele, contexte = _charger()
    meta = json.loads(META_PATH.read_text(encoding="utf-8"))

    df = lignes_vers_dataframe(lignes)
    if df.empty:
        return {"produits": [], "modele_entraine_le": meta["entraine_le"],
                "n_echantillons_entrainement": meta["n_echantillons"]}

    etats = etat_initial_par_article(df, contexte)

    produits = []
    for article, etat in etats.items():
        cible = mois_suivant(etat["dernier_mois"])
        if cible > mois_fin:
            produits.append({"article": article, "designation": etat["designation"], "previsions": []})
            continue

        lags_residus = list(etat["lags_residus"])
        t = etat["t_suivant"]
        previsions = []

        while cible <= mois_fin:
            mois_num = int(cible.split("-")[1])
            roll3 = float(np.mean(lags_residus[-3:])) if lags_residus else 0.0
            x = np.array([[
                contexte.encoder(contexte.article, article),
                contexte.encoder(contexte.famille, etat["famille"]),
                contexte.encoder(contexte.societe, etat["societe"]),
                mois_num,
                t,
                lags_residus[-1] if len(lags_residus) >= 1 else 0.0,
                lags_residus[-2] if len(lags_residus) >= 2 else 0.0,
                lags_residus[-3] if len(lags_residus) >= 3 else 0.0,
                roll3,
            ]], dtype=float)

            residu_pred = float(modele.predict(x)[0])
            y_pred = max(0.0, residu_pred + contexte.tendance(article, t))
            previsions.append({"mois": cible, "quantite_prevue_kg": round(y_pred, 1)})

            lags_residus = (lags_residus + [residu_pred])[-3:]
            t += 1
            cible = mois_suivant(cible)

        produits.append({"article": article, "designation": etat["designation"], "previsions": previsions})

    return {
        "produits": produits,
        "modele_entraine_le": meta["entraine_le"],
        "n_echantillons_entrainement": meta["n_echantillons"],
    }
