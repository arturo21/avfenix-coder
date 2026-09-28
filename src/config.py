# -*- coding: utf-8 -*-
import os
import requests
from dotenv import load_dotenv
from rich.console import Console

load_dotenv()
console = Console()

OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")

FALLBACK_FREE_MODELS = [
    "meta-llama/llama-3.1-8b-instruct:free",
    "meta-llama/llama-3-8b-instruct:free",
    "qwen/qwen-2-7b-instruct:free",
    "mistralai/mistral-7b-instruct:free",
    "microsoft/phi-3-medium-128k-instruct:free"
]

def get_available_free_models() -> list[str]:
    url = "https://openrouter.ai/api/v1/models"
    try:
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        data = response.json().get("data", [])
        
        free_models = []
        for model in data:
            model_id = model.get("id", "")
            if "gemini" in model_id:
                continue
                
            pricing = model.get("pricing", {})
            prompt_cost = float(pricing.get("prompt", 0))
            completion_cost = float(pricing.get("completion", 0))
            
            if (prompt_cost == 0.0 and completion_cost == 0.0) or model_id.endswith(":free"):
                free_models.append(model_id)
                
        free_models = [m for m in free_models if "gemini" not in m]
        return free_models if free_models else FALLBACK_FREE_MODELS
    except Exception as e:
        return FALLBACK_FREE_MODELS

def select_best_free_model(free_models: list[str]) -> str:
    preferences = [
        "meta-llama/llama-3.1-8b-instruct:free",
        "meta-llama/llama-3-8b-instruct:free",
        "qwen/qwen-2-7b-instruct:free",
        "mistralai/mistral-7b-instruct:free",
        "microsoft/phi-3-medium-128k-instruct:free"
    ]
    for pref in preferences:
        if pref in free_models:
            return pref
    clean_free_models = [m for m in free_models if "gemini" not in m]
    if clean_free_models:
        return clean_free_models[0]
    return "meta-llama/llama-3.1-8b-instruct:free"
