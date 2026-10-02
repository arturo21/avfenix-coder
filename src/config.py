# -*- coding: utf-8 -*-
"""
config.py - Configuración de Proveedores (OpenRouter & AnyAPI) y Filtrado Estricto de Modelos Gratuitos.
Garantiza que la aplicación SOLO utilice modelos 100% gratuitos sin coste alguno.
"""

import os
import logging
import requests
from dotenv import load_dotenv
from rich.console import Console

load_dotenv()
console = Console()
logger = logging.getLogger("Config")

OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
ANYAPI_API_KEY = os.getenv("ANYAPI_API_KEY")

OPENROUTER_BASE_URL = "https://openrouter.ai/api/v1"
ANYAPI_BASE_URL = "https://api.anyapi.ai/v1"

FALLBACK_FREE_MODELS = [
    "meta-llama/llama-3.1-8b-instruct:free",
    "meta-llama/llama-3-8b-instruct:free",
    "qwen/qwen-2-7b-instruct:free",
    "mistralai/mistral-7b-instruct:free",
    "microsoft/phi-3-medium-128k-instruct:free"
]

FALLBACK_OPENROUTER_FREE_MODELS = list(FALLBACK_FREE_MODELS)
FALLBACK_ANYAPI_FREE_MODELS = list(FALLBACK_FREE_MODELS)


def is_free_model(model_info: dict) -> bool:
    """
    Verifica de forma estricta que un modelo sea 100% gratuito.
    Filtra cualquier modelo que tenga coste mayor a cero en prompt o completion.
    """
    if not isinstance(model_info, dict):
        return False

    model_id = model_info.get("id", "")
    if not model_id:
        return False

    pricing = model_info.get("pricing", {})
    try:
        prompt_cost = float(pricing.get("prompt", 0) or 0)
        completion_cost = float(pricing.get("completion", 0) or 0)
    except (ValueError, TypeError):
        prompt_cost = 0.0
        completion_cost = 0.0

    # Excluir explícitamente cualquier modelo con coste monetario mayor a cero
    if prompt_cost > 0.0 or completion_cost > 0.0:
        return False

    # Aceptar si finaliza en :free o si los costos explícitos son 0
    if model_id.endswith(":free") or (prompt_cost == 0.0 and completion_cost == 0.0):
        return True

    return False


def get_openrouter_free_models() -> list[dict]:
    """
    Consulta los modelos de OpenRouter y devuelve SOLO los modelos gratuitos.
    """
    url = f"{OPENROUTER_BASE_URL}/models"
    api_key = OPENROUTER_API_KEY or ""
    headers = {"Authorization": f"Bearer {api_key}"} if api_key else {}
    candidates = []

    try:
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        data = response.json().get("data", [])

        for model in data:
            if is_free_model(model):
                model_id = model.get("id", "")
                if "gemini" in model_id.lower():
                    continue
                candidates.append({
                    "provider": "OpenRouter",
                    "model": model_id,
                    "base_url": OPENROUTER_BASE_URL,
                    "api_key": api_key
                })
    except Exception as e:
        logger.warning(f"Error al consultar lista de modelos en OpenRouter: {e}")

    if not candidates and api_key:
        for model_id in FALLBACK_OPENROUTER_FREE_MODELS:
            candidates.append({
                "provider": "OpenRouter",
                "model": model_id,
                "base_url": OPENROUTER_BASE_URL,
                "api_key": api_key
            })

    return candidates


def get_anyapi_free_models() -> list[dict]:
    """
    Consulta los modelos de AnyAPI y devuelve SOLO los modelos gratuitos.
    """
    url = f"{ANYAPI_BASE_URL}/models"
    api_key = ANYAPI_API_KEY or ""
    headers = {"Authorization": f"Bearer {api_key}"} if api_key else {}
    candidates = []

    try:
        response = requests.get(url, headers=headers, timeout=10)
        response.raise_for_status()
        data = response.json().get("data", [])

        for model in data:
            if is_free_model(model):
                model_id = model.get("id", "")
                if "gemini" in model_id.lower():
                    continue
                candidates.append({
                    "provider": "AnyAPI",
                    "model": model_id,
                    "base_url": ANYAPI_BASE_URL,
                    "api_key": api_key
                })
    except Exception as e:
        logger.warning(f"Error al consultar lista de modelos en AnyAPI: {e}")

    if not candidates and api_key:
        for model_id in FALLBACK_ANYAPI_FREE_MODELS:
            candidates.append({
                "provider": "AnyAPI",
                "model": model_id,
                "base_url": ANYAPI_BASE_URL,
                "api_key": api_key
            })

    return candidates


def get_available_free_models() -> list[dict]:
    """
    Obtiene la lista consolidada de modelos GRATUITOS de los proveedores habilitados
    (OpenRouter y AnyAPI). Garantiza de forma estricta que SOLO se devuelvan modelos gratuitos.
    """
    all_candidates = []

    if OPENROUTER_API_KEY:
        all_candidates.extend(get_openrouter_free_models())

    if ANYAPI_API_KEY:
        all_candidates.extend(get_anyapi_free_models())

    if not all_candidates:
        active_key = OPENROUTER_API_KEY or ANYAPI_API_KEY or ""
        provider_name = "AnyAPI" if ANYAPI_API_KEY else "OpenRouter"
        base_url = ANYAPI_BASE_URL if provider_name == "AnyAPI" else OPENROUTER_BASE_URL

        for model_id in FALLBACK_FREE_MODELS:
            all_candidates.append({
                "provider": provider_name,
                "model": model_id,
                "base_url": base_url,
                "api_key": active_key
            })

    return all_candidates


def select_best_free_model(candidates: list) -> dict | str:
    """
    Selecciona el mejor candidato a modelo gratuito basándose en la prioridad definida.
    Soporta tanto lista de diccionarios (nuevo formato) como lista de strings (legacy).
    """
    preferences = [
        "meta-llama/llama-3.1-8b-instruct:free",
        "meta-llama/llama-3-8b-instruct:free",
        "qwen/qwen-2-7b-instruct:free",
        "mistralai/mistral-7b-instruct:free",
        "microsoft/phi-3-medium-128k-instruct:free"
    ]

    if not candidates:
        return {
            "provider": "OpenRouter" if OPENROUTER_API_KEY else "AnyAPI",
            "model": "meta-llama/llama-3.1-8b-instruct:free",
            "base_url": OPENROUTER_BASE_URL if OPENROUTER_API_KEY else ANYAPI_BASE_URL,
            "api_key": OPENROUTER_API_KEY or ANYAPI_API_KEY or ""
        }

    if isinstance(candidates[0], str):
        for pref in preferences:
            if pref in candidates:
                return pref
        return candidates[0]

    for pref in preferences:
        for cand in candidates:
            if isinstance(cand, dict) and cand.get("model") == pref:
                return cand

    return candidates[0]
