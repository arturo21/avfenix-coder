# -*- coding: utf-8 -*-
"""
Unity MCP Server en Python (v2 - Resiliente y Optimizado)
Servidor MCP basado en el SDK oficial de Python ('mcp') para actuar como puente
entre AVFenix Coder (Host) y el Editor de Unity via un Listener HTTP/JSON local.
"""

import json
import urllib.request
import urllib.error
from typing import Optional, Dict, Any

# Manejo de importación resiliente para entornos sin SDK instalado
try:
    from mcp.server.fastmcp import FastMCP
    mcp = FastMCP("Unity-MCP-Server")
except ImportError:
    class FastMCPMock:
        def __init__(self, name: str):
            self.name = name
        def tool(self):
            def decorator(func):
                return func
            return decorator
        def run(self):
            print(f"[{self.name}] Servidor en modo mock (mcp no instalado).")
    mcp = FastMCPMock("Unity-MCP-Server")

UNITY_BRIDGE_URL = "http://localhost:8080/mcp/"

def send_to_unity(action: str, params: Optional[Dict[str, Any]] = None) -> str:
    """Envía una solicitud en formato JSON al puente C# en el Editor de Unity."""
    payload = {
        "action": action,
        "params": params or {}
    }
    data = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    req = urllib.request.Request(
        UNITY_BRIDGE_URL,
        data=data,
        headers={"Content-Type": "application/json; charset=utf-8"}
    )
    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            res_body = response.read().decode("utf-8")
            
            # Formatear la respuesta JSON para consumo óptimo del LLM
            try:
                parsed = json.loads(res_body)
                if isinstance(parsed, dict):
                    if parsed.get("status") == "error":
                        return f"[Error de Unity]: {parsed.get('message', 'Error desconocido')}"
                    elif "message" in parsed:
                        return parsed["message"]
                    elif "objects" in parsed:
                        objs = ", ".join(parsed["objects"])
                        return f"Escena '{parsed.get('scene', 'Activa')}': [{objs}]"
                    elif "logs" in parsed:
                        return "--- Consola de Unity ---\n" + "\n".join(parsed["logs"])
                return json.dumps(parsed, indent=2, ensure_ascii=False)
            except Exception:
                return res_body

    except urllib.error.URLError as e:
        return (
            f"[Error de Conexión con Unity]: No se pudo conectar a '{UNITY_BRIDGE_URL}'. "
            f"Asegúrate de que Unity esté abierto con el script 'UnityMCPBridge.cs' activo. ({e})"
        )
    except Exception as e:
        return f"[Error al comunicarse con Unity]: {e}"

# =========================================================================
# 🛠️ HERRAMIENTAS MCP EXPUESTAS A AVFENIX CODER
# =========================================================================

@mcp.tool()
def unity_get_scene_hierarchy() -> str:
    """Obtiene la lista y estructura jerárquica de todos los GameObjects en la escena activa de Unity."""
    return send_to_unity("get_hierarchy")

@mcp.tool()
def unity_create_game_object(name: str, primitive_type: str = "Empty") -> str:
    """
    Crea un nuevo GameObject en la escena activa de Unity.
    :param name: Nombre que se le asignará al objeto.
    :param primitive_type: Tipo de primitiva ('Empty', 'Cube', 'Sphere', 'Cylinder', 'Plane', 'Quad', 'Sprite').
    """
    return send_to_unity("create_gameobject", {"name": name, "type": primitive_type})

@mcp.tool()
def unity_set_transform(
    object_name: str,
    position_x: float = 0.0,
    position_y: float = 0.0,
    position_z: float = 0.0,
    rotation_x: float = 0.0,
    rotation_y: float = 0.0,
    rotation_z: float = 0.0,
    scale_x: float = 1.0,
    scale_y: float = 1.0,
    scale_z: float = 1.0
) -> str:
    """Modifica la posición, rotación Euler y escala de un GameObject en la escena."""
    return send_to_unity("set_transform", {
        "name": object_name,
        "position": [position_x, position_y, position_z],
        "rotation": [rotation_x, rotation_y, rotation_z],
        "scale": [scale_x, scale_y, scale_z]
    })

@mcp.tool()
def unity_add_component(object_name: str, component_type: str) -> str:
    """
    Añade un componente nativo o personalizado a un GameObject.
    Ejemplos de component_type: 'Rigidbody', 'BoxCollider', 'AudioSource', 'Light', 'PlayerController'.
    """
    return send_to_unity("add_component", {"name": object_name, "component": component_type})

@mcp.tool()
def unity_create_script(script_name: str, code_content: str) -> str:
    """
    Crea o sobrescribe un script de C# dentro de la carpeta 'Assets/Scripts/' del proyecto de Unity.
    :param script_name: Nombre de la clase C# (ej. 'PlayerController').
    :param code_content: Código fuente C# completo.
    """
    return send_to_unity("create_script", {"name": script_name, "code": code_content})

@mcp.tool()
def unity_get_console_logs() -> str:
    """Consulta las últimas entradas de la Consola de Unity (errores de compilación, advertencias o Debug.Log)."""
    return send_to_unity("get_logs")

@mcp.tool()
def unity_toggle_play_mode(state: bool) -> str:
    """Activa (True) o detiene (False) la ejecución en tiempo real (Play Mode) dentro del Editor de Unity."""
    return send_to_unity("toggle_play", {"state": state})

if __name__ == "__main__":
    if hasattr(mcp, 'run'):
        mcp.run()
