import openai
from rich.console import Console
from src.config import OPENROUTER_API_KEY, get_available_free_models, select_best_free_model, FALLBACK_FREE_MODELS

console = Console()

def run_agent(prompt: str, forced_model: str = None, allow_paid: bool = False):
    """
    Ejecuta la llamada al agente utilizando un modelo de OpenRouter.
    Implementa un bucle de reintentos automático que cambia de API gratuita
    si el modelo seleccionado falla (evitando bloqueos por errores 404).
    """
    console.print(f"[blue]Instrucción recibida:[/blue] {prompt}")
    
    if not OPENROUTER_API_KEY:
        console.print("[red]Error: API Key de OpenRouter ausente en el archivo .env[/red]")
        return
        
    client = openai.OpenAI(
        base_url="https://openrouter.ai/api/v1",
        api_key=OPENROUTER_API_KEY,
    )
    
    # Lista de candidatos a intentar en orden de preferencia
    candidates = []

    # Caso A: El usuario intentó forzar un modelo
    if forced_model:
        # Exclusión de seguridad si es de pago y no se permite explícitamente
        if not forced_model.endswith(":free") and not allow_paid:
            console.print("[bold red]🛡️ BLOQUEO DE SEGURIDAD ACTIVADO:[/bold red]")
            console.print(f"[yellow]El modelo forzado '{forced_model}' NO es gratuito y consumiría saldo.[/yellow]")
            
            # Caso especial detectado por el usuario: gemini-2.5-flash ya no es gratis
            if "gemini" in forced_model:
                console.print("[yellow]Gemini ya no está disponible de forma gratuita en OpenRouter. Cambiando a Llama 3.1 como reemplazo seguro...[/yellow]")
                candidates = ["meta-llama/llama-3.1-8b-instruct:free"] + FALLBACK_FREE_MODELS
            else:
                suggested_free = f"{forced_model}:free"
                console.print(f"[cyan]Corrigiendo automáticamente al modelo gratuito equivalente: {suggested_free}[/cyan]")
                candidates = [suggested_free] + FALLBACK_FREE_MODELS
        else:
            candidates = [forced_model]
    else:
        # Caso B: Selección inteligente dinámica (Comportamiento estándar)
        console.print("[yellow]Buscando modelos gratuitos disponibles en OpenRouter...[/yellow]")
        free_models = get_available_free_models()
        best_model = select_best_free_model(free_models)
        
        # Construir lista de reintentos priorizando el mejor, seguido de los fallbacks estables
        candidates = [best_model] + [m for m in FALLBACK_FREE_MODELS if m != best_model]

    # Bucle de reintentos resiliente
    for model_to_try in candidates:
        console.print(f"[bold green]Intentando conectar usando el modelo gratuito:[/bold green] {model_to_try}")
        
        try:
            response = client.chat.completions.create(
                model=model_to_try,
                messages=[
                    {"role": "system", "content": "Eres AVFenix Coder, un asistente de codificación experto e inteligente."},
                    {"role": "user", "content": prompt}
                ]
            )
            
            # Obtener respuesta exitosa (Indexación  corregida)
            answer = response.choices[0].message.content
            console.print("\n[bold green]Respuesta del Agente:[/bold green]")
            console.print(answer)
            return  # Salir exitosamente al recibir respuesta
            
        except Exception as e:
            error_msg = str(e)
            console.print(f"[yellow]⚠️ El modelo '{model_to_try}' falló (Error: {error_msg}).[/yellow]")
            
            # Si el usuario forzó explícitamente el modelo y permitía pago, no rotamos automáticamente a gratis
            if forced_model and allow_paid:
                console.print("[red]La llamada falló con el modelo de pago forzado. Deteniendo ejecución.[/red]")
                return
                
            console.print("[cyan]Intentando recuperar usando el siguiente modelo gratuito de la lista de respaldo...[/cyan]")
            
    console.print("[red]❌ Error crítico: Todos los modelos gratuitos disponibles fallaron o están temporalmente fuera de servicio.[/red]")