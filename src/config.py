import os
import requests
from dotenv import load_dotenv
from rich.console import Console

load_dotenv()
console = Console()

OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY")
# Fallback genérico para evitar errores NameError / ImportError
FALLBACK_FREE_MODELS = ["openrouter/free"]

def get_available_free_models() -> list[str]:
    """
    Consulta la API pública de OpenRouter y extrae dinámicamente todos los modelos
    cuyo costo de prompt y completion sea 0 o que terminen con la extensión `:free`.
    Excluye cualquier modelo de la familia Gemini.
    """
    url = "https://openrouter.ai/api/v1/models"
    try:
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        data = response.json().get("data", [])
        
        free_models = []
        for model in data:
            model_id = model.get("id", "")
            
            # Exclusión explícita de modelos Gemini
            if "gemini" in model_id.lower():
                continue
                
            pricing = model.get("pricing", {})
            prompt_cost = float(pricing.get("prompt", 0))
            completion_cost = float(pricing.get("completion", 0))
            
            # Verificar si el modelo es gratuito según sus métricas de precio o sufijo
            if (prompt_cost == 0.0 and completion_cost == 0.0) or model_id.endswith(":free"):
                free_models.append(model_id)
                
        return free_models
    except Exception as e:
        console.print(f"[yellow]Advertencia: No se pudo obtener la lista de modelos de OpenRouter ({e}).[/yellow]")
        return []

def select_best_free_model(free_models: list[str]) -> str:
    """
    Selecciona un modelo gratuito a partir del array obtenido de OpenRouter.
    Si la lista está vacía o no hay coincidencia, retorna el router oficial 'openrouter/free'.
    """
    # Filtrar activamente residuos de Gemini en caso de que alguno haya pasado
    clean_free_models = [m for m in free_models if "gemini" not in m.lower()]
    
    if clean_free_models:
        # Devuelve el primer modelo gratuito reportado por OpenRouter
        return clean_free_models[0]
        
    # Si la API no devolvió nada o falló, usa el router automático de modelos gratuitos de OpenRouter
    return "openrouter/free"