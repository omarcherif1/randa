"""Contrats d'échange avec Laravel — validés automatiquement par Pydantic.

Règle 4.5 (héritée du calcul PHP qu'on remplace) : le modèle apprend
UNIQUEMENT sur la quantité COMMANDÉE, jamais livrée. Laravel ne transmet que
des commandes locales (scope local) — ce service ne sait rien de l'export,
il ne fait que ce qu'on lui donne.
"""

from __future__ import annotations

from pydantic import BaseModel, Field


class LigneHistorique(BaseModel):
    article: str
    designation: str = ""
    famille: str | None = None
    societe: str | None = None
    mois: str = Field(pattern=r"^\d{4}-\d{2}$")  # AAAA-MM
    qte_commandee_kg: float = Field(ge=0)


class EntrainementRequest(BaseModel):
    lignes: list[LigneHistorique]


class EntrainementResponse(BaseModel):
    entraine_le: str
    n_echantillons: int
    n_produits: int
    mae_kg: float | None
    wape: float | None
    r2: float | None


class PredictionRequest(BaseModel):
    lignes: list[LigneHistorique]
    mois_fin: str = Field(pattern=r"^\d{4}-\d{2}$")


class MoisPrevu(BaseModel):
    mois: str
    quantite_prevue_kg: float


class ProduitPrevu(BaseModel):
    article: str
    designation: str
    previsions: list[MoisPrevu]


class PredictionResponse(BaseModel):
    produits: list[ProduitPrevu]
    modele_entraine_le: str
    n_echantillons_entrainement: int


class StatutModele(BaseModel):
    entraine: bool
    entraine_le: str | None = None
    n_echantillons: int | None = None
    n_produits: int | None = None
    mae_kg: float | None = None
    wape: float | None = None
    r2: float | None = None
