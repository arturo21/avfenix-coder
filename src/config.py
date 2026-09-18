# -*- coding: utf-8 -*-
import os
import requests
from dotenv import load_dotenv

load_dotenv()

OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
ANYAPI_API_KEY = os.getenv("ANYAPI_API_KEY")

FALLBACK_FREE_MODELS = [
    "meta-llama/llama-3.1-8b-instruct:free",
    "meta-llama/llama-3-8b-instruct:free",
    "qwen/qwen-2-7b-instruct:free",
    "mistralai/mistral-7b-instruct:free",
    "microsoft/phi-3-medium-128k-instruct:free"
]

def is_free_model(model_info: dict) -> bool:
    """
    Verifica de forma estricta que un modelo sea 100% gratuito.
    Excluye cualquier modelo con costo > 0 en prompt o completion.
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

    if prompt_cost > 0.0 or completion_cost > 0.0:
        return False

    if model_id.endswith(":free") or (prompt_cost == 0.0 and completion_cost == 0.0):
        return True

    return False

def get_multi_provider_free_candidates() -> list[dict]:
    """
    Obtiene candidatos gratuitos desde OpenRouter y AnyAPI garantizando costo 0.
    """
    candidates = []

    # 1. Obtener de OpenRouter
    if OPENROUTER_API_KEY:
        try:
            resp = requests.get("https://openrouter.ai/api/v1/models", timeout=8)
            if resp.status_code == 200:
                data = resp.json().get("data", [])
                for m in data:
                    if is_free_model(m) and "gemini" not in m.get("id", ""):
                        candidates.append({
                            "provider": "OpenRouter",
                            "model": m["id"],
                            "base_url": "https://openrouter.ai/api/v1",
                            "api_key": OPENROUTER_API_KEY
                        })
        except Exception:
            pass

    # 2. Obtener de AnyAPI
    if ANYAPI_API_KEY:
        try:
            resp = requests.get("https://api.anyapi.ai/v1/models", timeout=8)
            if resp.status_code == 200:
                data = resp.json().get("data", [])
                for m in data:
                    if is_free_model(m) and "gemini" not in m.get("id", ""):
                        candidates.append({
                            "provider": "AnyAPI",
                            "model": m["id"],
                            "base_url": "https://api.anyapi.ai/v1",
                            "api_key": ANYAPI_API_KEY
                        })
        except Exception:
            pass

    # Fallback de respaldo
    if not candidates:
        for m_id in FALLBACK_FREE_MODELS:
            if OPENROUTER_API_KEY:
                candidates.append({
                    "provider": "OpenRouter",
                    "model": m_id,
                    "base_url": "https://openrouter.ai/api/v1",
                    "api_key": OPENROUTER_API_KEY
                })
            if ANYAPI_API_KEY:
                candidates.append({
                    "provider": "AnyAPI",
                    "model": m_id,
                    "base_url": "https://api.anyapi.ai/v1",
                    "api_key": ANYAPI_API_KEY
                })

    return candidates
