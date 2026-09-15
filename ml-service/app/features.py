"""Construction des features — UN SEUL endroit, partagé entraînement/prédiction.

Modèle global (pas un modèle par produit — décision produit) : chaque ligne
d'entraînement est un couple (article, mois). Les features encodent qui est
le produit (article/famille/société) et où il en est dans le temps (mois de
l'année = saisonnalité, valeurs des mois précédents = mémoire à court terme).

Décomposition tendance + résidu (important) : un modèle à arbres (Random
Forest) ne peut JAMAIS prédire une valeur hors de la plage vue à
l'entraînement — sur un produit en nette hausse, ses prévisions plafonnent
au lieu de continuer à monter. On calcule donc, PAR ARTICLE, une tendance
linéaire simple (même formule que l'ancien moteur PHP remplacé), et le
modèle n'apprend que le RÉSIDU autour de cette tendance (saisonnalité,
à-coups, effets croisés entre produits) — la tendance elle-même est
rajoutée après coup, donc elle continue de s'extrapoler normalement.
"""

from __future__ import annotations

from dataclasses import dataclass, field

import numpy as np
import pandas as pd

FEATURE_COLUMNS = [
    "article_id",
    "famille_id",
    "societe_id",
    "mois_num",
    "t",
    "lag_1",
    "lag_2",
    "lag_3",
    "rolling_mean_3",
]

INCONNU = -1  # article/famille/société jamais vu à l'entraînement


def regression_lineaire(t: list[int], y: list[float]) -> tuple[float, float]:
    """Pente + ordonnée à l'origine (moindres carrés) — même formule que
    `ForecastEngine::regressionLineaire()` côté PHP, pour rester cohérent
    avec la définition de "tendance" déjà validée dans ce projet."""
    n = len(y)
    if n == 0:
        return 0.0, 0.0
    if n == 1:
        return 0.0, y[0]
    t_arr = np.asarray(t, dtype=float)
    y_arr = np.asarray(y, dtype=float)
    denom = n * np.sum(t_arr**2) - np.sum(t_arr) ** 2
    if abs(denom) < 1e-9:
        return 0.0, float(np.mean(y_arr))
    pente = (n * np.sum(t_arr * y_arr) - np.sum(t_arr) * np.sum(y_arr)) / denom
    ordonnee = (np.sum(y_arr) - pente * np.sum(t_arr)) / n
    return float(pente), float(ordonnee)


@dataclass
class ContexteModele:
    """Tout ce qu'il faut retenir de l'entraînement pour encoder une requête
    de prédiction EXACTEMENT de la même façon. Persisté avec le modèle."""

    article: dict[str, int] = field(default_factory=dict)
    famille: dict[str, int] = field(default_factory=dict)
    societe: dict[str, int] = field(default_factory=dict)
    # article -> (pente, ordonnée) de sa tendance linéaire propre
    tendances: dict[str, tuple[float, float]] = field(default_factory=dict)

    @staticmethod
    def apprendre_categorie(valeurs: pd.Series) -> dict[str, int]:
        distinctes = sorted(v for v in valeurs.dropna().unique() if v != "")
        return {v: i for i, v in enumerate(distinctes)}

    def encoder(self, mapping: dict[str, int], valeur: str | None) -> int:
        if valeur is None or valeur == "":
            return INCONNU
        return mapping.get(valeur, INCONNU)

    def tendance(self, article: str, t: int) -> float:
        pente, ordonnee = self.tendances.get(article, (0.0, 0.0))
        return pente * t + ordonnee


def _mois_num(mois: str) -> int:
    return int(mois.split("-")[1])


def _mois_index(mois: str) -> int:
    """Index linéaire (années × 12 + mois) pour trier/comparer sans ambiguïté."""
    a, m = mois.split("-")
    return int(a) * 12 + int(m)


def lignes_vers_dataframe(lignes: list[dict]) -> pd.DataFrame:
    df = pd.DataFrame(lignes)
    if df.empty:
        return df
    df["mois_index"] = df["mois"].map(_mois_index)
    df = df.sort_values(["article", "mois_index"]).reset_index(drop=True)
    return df


def apprendre_tendances(df: pd.DataFrame) -> dict[str, tuple[float, float]]:
    tendances: dict[str, tuple[float, float]] = {}
    for article, groupe in df.groupby("article", sort=False):
        groupe = groupe.sort_values("mois_index")
        valeurs = groupe["qte_commandee_kg"].tolist()
        tendances[article] = regression_lineaire(list(range(len(valeurs))), valeurs)
    return tendances


def construire_table_entrainement(
    df: pd.DataFrame, contexte: ContexteModele
) -> tuple[np.ndarray, np.ndarray, list[str]]:
    """Une ligne d'entraînement par (article, mois) observé. La cible est le
    RÉSIDU (valeur réelle moins tendance de l'article), pas la valeur brute —
    voir le docstring du module."""
    lignes_x: list[list[float]] = []
    lignes_y: list[float] = []
    articles: list[str] = []

    for article, groupe in df.groupby("article", sort=False):
        groupe = groupe.sort_values("mois_index")
        valeurs = groupe["qte_commandee_kg"].tolist()
        mois_liste = groupe["mois"].tolist()
        famille = groupe["famille"].iloc[0] if "famille" in groupe else None
        societe = groupe["societe"].iloc[0] if "societe" in groupe else None
        residus = [v - contexte.tendance(article, t) for t, v in enumerate(valeurs)]

        for t, mois in enumerate(mois_liste):
            hist_resid = residus[:t]  # mois strictement antérieurs
            lag1 = hist_resid[-1] if len(hist_resid) >= 1 else 0.0
            lag2 = hist_resid[-2] if len(hist_resid) >= 2 else 0.0
            lag3 = hist_resid[-3] if len(hist_resid) >= 3 else 0.0
            roll3 = float(np.mean(hist_resid[-3:])) if hist_resid else 0.0

            lignes_x.append([
                contexte.encoder(contexte.article, article),
                contexte.encoder(contexte.famille, famille),
                contexte.encoder(contexte.societe, societe),
                _mois_num(mois),
                t,
                lag1,
                lag2,
                lag3,
                roll3,
            ])
            lignes_y.append(residus[t])
            articles.append(article)

    return np.array(lignes_x, dtype=float), np.array(lignes_y, dtype=float), articles


def etat_initial_par_article(df: pd.DataFrame, contexte: ContexteModele) -> dict[str, dict]:
    """Dernier état connu de chaque article (pour démarrer la projection
    récursive en prédiction) : ses 3 derniers RÉSIDUS, sa position `t`
    suivante, sa famille/société, sa désignation, son dernier mois observé."""
    etats: dict[str, dict] = {}
    for article, groupe in df.groupby("article", sort=False):
        groupe = groupe.sort_values("mois_index")
        valeurs = groupe["qte_commandee_kg"].tolist()
        residus = [v - contexte.tendance(article, t) for t, v in enumerate(valeurs)]
        etats[article] = {
            "designation": groupe["designation"].iloc[-1] if "designation" in groupe else article,
            "famille": groupe["famille"].iloc[-1] if "famille" in groupe else None,
            "societe": groupe["societe"].iloc[-1] if "societe" in groupe else None,
            "dernier_mois": groupe["mois"].iloc[-1],
            "t_suivant": len(valeurs),
            "lags_residus": (residus[-3:] if len(residus) >= 3 else [0.0] * (3 - len(residus)) + residus),
        }
    return etats


def mois_suivant(mois: str, pas: int = 1) -> str:
    a, m = (int(x) for x in mois.split("-"))
    total = a * 12 + (m - 1) + pas
    return f"{total // 12:04d}-{total % 12 + 1:02d}"
