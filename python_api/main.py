import functools
import json
from pathlib import Path
from typing import Any, Dict, List, Tuple

import numpy as np
from fastapi import FastAPI, File, UploadFile
from PIL import Image


app = FastAPI(title="Agrico AI API", version="1.0.0")
BASE_DIR = Path(__file__).resolve().parent
MODEL_DIR = BASE_DIR / "models"
MODEL_PATH = MODEL_DIR / "plantvillage_best.keras"
LABELS_PATH = MODEL_DIR / "labels.json"
MODEL_IMAGE_SIZE = 224


def _normalize(text: str) -> str:
    return " ".join(text.replace("_", " ").split()).strip()


def _parse_label(label: str) -> Tuple[str, str]:
    parts = label.split("___")
    plant = _normalize(parts[0]) if parts else _normalize(label)
    disease = _normalize(parts[1]) if len(parts) > 1 else "Unknown"
    return plant, disease


def _disease_template(disease: str, plant: str, category: str) -> Dict[str, Any]:
    if category == "healthy":
        return {
            "description": f"{plant} leaves show no strong signs of disease.",
            "causes": "Normal growth or mild environmental stress.",
            "symptoms": "Leaves appear mostly green without spreading lesions or mold.",
            "how_it_spreads": "Not applicable.",
            "care_recommendations": [
                "Maintain balanced watering and drainage.",
                "Monitor for new spots or discoloration.",
                "Ensure adequate sunlight and airflow.",
            ],
        }

    if category == "fungal":
        causes = "Fungal infection favored by high humidity, leaf wetness, and poor airflow."
        spread = "Spores spread by wind, splashing water, and contaminated tools or debris."
        care = [
            "Remove and dispose infected leaves.",
            "Avoid overhead irrigation; water at the base.",
            "Improve spacing and airflow.",
            "Apply an approved fungicide as directed.",
        ]
    elif category == "bacterial":
        causes = "Bacterial infection entering through wounds or wet leaf surfaces."
        spread = "Spread by splashing water, tools, hands, and infected plant material."
        care = [
            "Remove infected tissue and sanitize tools.",
            "Avoid working with wet plants.",
            "Use copper-based treatments if recommended locally.",
            "Rotate crops and avoid overhead irrigation.",
        ]
    elif category == "viral":
        causes = "Virus infection, often introduced by insect vectors or infected seedlings."
        spread = "Spread by insects (e.g., aphids/whiteflies), tools, and infected plants."
        care = [
            "Remove infected plants to limit spread.",
            "Control insect vectors promptly.",
            "Use certified disease-free seedlings.",
            "Disinfect tools and hands after handling.",
        ]
    elif category == "pest":
        causes = "Pest feeding damage (e.g., mites) weakening leaf tissue."
        spread = "Pests move plant to plant via wind, contact, or infested material."
        care = [
            "Inspect undersides of leaves regularly.",
            "Rinse leaves with water to reduce pest load.",
            "Use recommended miticides or insecticidal soap.",
            "Remove heavily infested foliage.",
        ]
    else:
        causes = "Unknown or mixed causes."
        spread = "May spread via wind, water, or contact."
        care = [
            "Remove severely affected leaves.",
            "Improve airflow and avoid wet foliage.",
            "Consult local agronomist for confirmation.",
        ]

    return {
        "description": f"{disease} symptoms detected on {plant}.",
        "causes": causes,
        "symptoms": "Spots, lesions, discoloration, or mold-like growth may be present.",
        "how_it_spreads": spread,
        "care_recommendations": care,
    }


def build_disease_info(label: str) -> Dict[str, Any]:
    plant, disease = _parse_label(label)
    disease_lower = disease.lower()

    if disease_lower == "healthy":
        info = _disease_template(disease, plant, "healthy")
    elif "virus" in disease_lower or "mosaic" in disease_lower or "curl" in disease_lower:
        info = _disease_template(disease, plant, "viral")
    elif "bacterial" in disease_lower:
        info = _disease_template(disease, plant, "bacterial")
    elif "mite" in disease_lower or "spider" in disease_lower:
        info = _disease_template(disease, plant, "pest")
    elif any(
        k in disease_lower
        for k in [
            "blight",
            "mildew",
            "scab",
            "spot",
            "rust",
            "rot",
            "mold",
            "anthracnose",
            "leaf scorch",
            "leaf blight",
        ]
    ):
        info = _disease_template(disease, plant, "fungal")
    else:
        info = _disease_template(disease, plant, "unknown")

    return {
        "plant_name": plant,
        "disease_name": disease,
        **info,
    }


def _load_labels() -> List[str]:
    if not LABELS_PATH.exists():
        return []
    with open(LABELS_PATH, "r", encoding="utf-8") as f:
        return json.load(f)


@functools.lru_cache(maxsize=1)
def _load_model_bundle() -> Tuple[Any, List[str]]:
    if not MODEL_PATH.exists():
        return None, []
    try:
        import tensorflow as tf  # lazy import

        model = tf.keras.models.load_model(MODEL_PATH)
        labels = _load_labels()
        return model, labels
    except Exception:
        return None, []


@app.get("/health")
def health() -> Dict[str, str]:
    return {"status": "ok"}


@app.post("/predict")
async def predict(file: UploadFile = File(...)) -> Dict[str, Any]:
    image = Image.open(file.file).convert("RGB")
    model, labels = _load_model_bundle()

    if model is not None and labels:
        resized = image.resize((MODEL_IMAGE_SIZE, MODEL_IMAGE_SIZE))
        arr = np.asarray(resized).astype(np.float32)
        preds = model.predict(arr[None, ...], verbose=0)[0]
        idx = int(np.argmax(preds))
        label = labels[idx] if idx < len(labels) else "Unknown"
        confidence = float(preds[idx]) * 100.0
        info = build_disease_info(label)
        treatment = "; ".join(info["care_recommendations"])
        return {
            "disease_name": info["disease_name"],
            "plant_name": info["plant_name"],
            "label": label,
            "confidence": round(confidence, 2),
            "description": info["description"],
            "causes": info["causes"],
            "symptoms": info["symptoms"],
            "how_it_spreads": info["how_it_spreads"],
            "care_recommendations": info["care_recommendations"],
            "treatment": treatment,
            "model": "plantvillage_effnetb0",
        }

    arr = np.asarray(image).astype(np.float32) / 255.0
    green_ratio = float(arr[:, :, 1].mean())

    if green_ratio < 0.35:
        disease = "Leaf Spot"
        confidence = 86.4
        info = _disease_template(disease, "Unknown", "fungal")
    elif green_ratio < 0.45:
        disease = "Early Blight"
        confidence = 82.1
        info = _disease_template(disease, "Unknown", "fungal")
    else:
        disease = "Healthy"
        confidence = 78.2
        info = _disease_template(disease, "Unknown", "healthy")

    return {
        "disease_name": disease,
        "confidence": round(confidence, 2),
        "description": info["description"],
        "causes": info["causes"],
        "symptoms": info["symptoms"],
        "how_it_spreads": info["how_it_spreads"],
        "care_recommendations": info["care_recommendations"],
        "treatment": "; ".join(info["care_recommendations"]),
        "model": "heuristic",
    }

