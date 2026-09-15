"""API du service de prévision ML (règle 4.5, v2 — remplace le calcul PHP
pour la prévision de la demande, sans y toucher pour la satisfaction/rupture/
retard, qui restent dans Laravel).

Ce service ne connaît RIEN de la base de données Randa : Laravel lui envoie
les données déjà filtrées (local uniquement, quantité commandée) à chaque
appel — le découplage est total, ce service pourrait tourner n'importe où.
"""

from __future__ import annotations

import logging

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

from . import model
from .schemas import (
    EntrainementRequest,
    EntrainementResponse,
    PredictionRequest,
    PredictionResponse,
    StatutModele,
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("previsions-ml")

app = FastAPI(
    title="Randa — Service de prévision ML",
    description="Modèle global (Random Forest) de prévision de la demande, "
    "règle métier 4.5 : apprend sur la quantité commandée locale uniquement.",
    version="1.0.0",
)

# Laravel (php artisan serve, 127.0.0.1:8000) est le seul appelant prévu.
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://127.0.0.1:8000", "http://localhost:8000"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


@app.get("/health")
def health():
    return {"status": "ok"}


@app.get("/status", response_model=StatutModele)
def status():
    return model.lire_statut()


@app.post("/train", response_model=EntrainementResponse)
def train(payload: EntrainementRequest):
    lignes = [ligne.model_dump() for ligne in payload.lignes]
    if len(lignes) < 10:
        raise HTTPException(422, "Pas assez de données pour entraîner un modèle (10 lignes minimum).")

    try:
        meta = model.entrainer(lignes)
    except ValueError as e:
        raise HTTPException(422, str(e)) from e

    logger.info("Modèle entraîné : %s", meta)
    return meta


@app.post("/predict", response_model=PredictionResponse)
def predict(payload: PredictionRequest):
    lignes = [ligne.model_dump() for ligne in payload.lignes]
    try:
        resultat = model.predire(lignes, payload.mois_fin)
    except model.ModeleNonEntraineError as e:
        raise HTTPException(409, str(e)) from e

    return resultat
