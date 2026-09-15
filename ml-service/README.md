# Service de prévision ML (règle 4.5)

Petit service FastAPI, séparé de Laravel, qui entraîne et sert un modèle de
prévision de la demande (Random Forest global — un seul modèle pour tous les
produits). Laravel lui envoie les données déjà filtrées (commandes locales
uniquement, quantité **commandée**, jamais livrée) à chaque appel ; ce
service ne se connecte jamais lui-même à la base de données.

## Installation (une fois)

```
cd ml-service
python -m venv venv
venv\Scripts\pip install -r requirements.txt
```

## Lancer le service

```
cd ml-service
venv\Scripts\python -m uvicorn app.main:app --port 8001
```

Le service écoute sur `http://127.0.0.1:8001`. À lancer **en plus** de
`php artisan serve` — les deux tournent en parallèle. Doc interactive :
`http://127.0.0.1:8001/docs`.

## Endpoints

- `GET /health` — vérification simple.
- `GET /status` — le modèle est-il entraîné ? depuis quand ? avec quelle précision (MAE/WAPE/R²) ?
- `POST /train` — (ré)entraîne le modèle sur les lignes envoyées (déclenché depuis le bouton Administration de la page Prévisions).
- `POST /predict` — prévisions mois par mois jusqu'à `mois_fin`, pour chaque produit envoyé.

## Le modèle, en clair

Random Forest de scikit-learn, **un seul modèle** entraîné sur tous les
produits ensemble (pas un modèle par produit — avec ~8 mois d'historique par
article, un modèle isolé par produit n'aurait presque rien à apprendre).

Chaque ligne d'entraînement = un couple (article, mois). Features : article,
famille, société (encodés), mois de l'année (saisonnalité), position dans le
temps, et résidu des 1 à 3 mois précédents (mémoire courte).

**Décomposition tendance + résidu** : un modèle à arbres ne peut jamais
prédire une valeur hors de la plage vue à l'entraînement — sur un produit en
nette hausse, ses prévisions plafonneraient au lieu de continuer à monter.
On calcule donc, par article, une tendance linéaire simple (même formule que
l'ancien moteur de calcul PHP), et le modèle n'apprend que le résidu autour
de cette tendance ; la tendance est rajoutée après coup, donc elle continue
de s'extrapoler normalement.

**Précision mesurée honnêtement** : à chaque entraînement, le dernier mois
observé de chaque article sert de test (jamais vu par le modèle qui produit
la métrique), avant de réentraîner sur tout pour la mise en production —
même esprit que le backtest walk-forward déjà en place côté PHP.

**WAPE, pas MAPE** : la précision relative est mesurée en WAPE (somme des
erreurs absolues ÷ somme des vraies valeurs), pas en MAPE classique (moyenne
des erreurs en % produit par produit). Avec des produits dont la quantité
commandée descend parfois à quelques kg, la MAPE classique explose (une
erreur de 200 kg sur un produit à 10 kg = 2000 %) et noie la précision
réelle sur les gros volumes — ceux qui comptent le plus pour la
planification. WAPE pondère chaque produit par son propre volume.
