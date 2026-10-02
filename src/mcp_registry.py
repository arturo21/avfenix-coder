# -*- coding: utf-8 -*-
"""
mcp_registry.py - Gestor y Catálogo de Servidores MCP (Model Context Protocol).
Permite registrar, almacenar, listar y conectar servidores MCP locales (STDIO) o remotos (SSE)
como PostgreSQL, GitHub, Docker, Playwright, Unity, etc.
"""

import os
import json
import logging
from typing import Dict, Any, List, Optional

logger = logging.getLogger("MCPRegistry")

DEFAULT_REGISTRY_FILE = ".avfenix_mcp/servers.json"

# Catálogo oficial pre-configurado de servidores MCP conocidos
OFFICIAL_MCP_SERVERS = {
    "unity-editor": {
        "id": "unity-editor",
        "name": "Unity Editor MCP Bridge",
        "transport_type": "stdio",
        "command": "python3",
        "args": ["src/unity_mcp_server.py"],
        "description": "Puente MCP para automatización y control del Editor de Unity",
        "enabled": True,
        "roots": ["."]
    },
    "github-connector": {
        "id": "github-connector",
        "name": "GitHub Official MCP",
        "transport_type": "stdio",
        "command": "npx",
        "args": ["-y", "@modelcontextprotocol/server-github"],
        "env": {"GITHUB_PERSONAL_ACCESS_TOKEN": ""},
        "description": "Acceso a repositorios, PRs e issues de GitHub",
        "enabled": False,
        "roots": ["."]
    },
    "postgres-db": {
        "id": "postgres-db",
        "name": "PostgreSQL Database MCP",
        "transport_type": "stdio",
        "command": "npx",
        "args": ["-y", "@modelcontextprotocol/server-postgres", "postgresql://localhost/app_db"],
        "description": "Inspección y consultas avanzadas en bases de datos PostgreSQL",
        "enabled": False,
        "roots": ["."]
    },
    "filesystem-sandbox": {
        "id": "filesystem-sandbox",
        "name": "Filesystem Secure MCP",
        "transport_type": "stdio",
        "command": "npx",
        "args": ["-y", "@modelcontextprotocol/server-filesystem", "."],
        "description": "Acceso seguro a archivos delimitados por Roots",
        "enabled": True,
        "roots": ["."]
    }
}


class MCPRegistry:
    """
    Administra el catálogo de servidores MCP registrados en el proyecto.
    Maneja persistencia JSON, validación de configuraciones, roots de seguridad y estado de activación.
    """

    def __init__(self, base_dir: str = ".", registry_file: str = DEFAULT_REGISTRY_FILE):
        self.base_dir = os.path.abspath(base_dir)
        self.registry_path = os.path.join(self.base_dir, registry_file)
        self.servers: Dict[str, Dict[str, Any]] = {}
        self._ensure_storage()
        self.load_registry()

    def _ensure_storage(self):
        """Asegura la creación del directorio de configuración MCP."""
        os.makedirs(os.path.dirname(self.registry_path), exist_ok=True)

    def load_registry(self) -> Dict[str, Dict[str, Any]]:
        """
        Carga el registro desde disco. Si no existe, inicializa con el catálogo oficial pre-configurado.
        """
        if not os.path.exists(self.registry_path):
            self.servers = dict(OFFICIAL_MCP_SERVERS)
            self.save_registry()
            return self.servers

        try:
            with open(self.registry_path, "r", encoding="utf-8") as f:
                data = json.load(f)
                if isinstance(data, dict):
                    self.servers = data
                else:
                    self.servers = dict(OFFICIAL_MCP_SERVERS)
            logger.info(f"Catálogo MCP cargado desde '{self.registry_path}' ({len(self.servers)} servidores).")
        except Exception as e:
            logger.error(f"Error al leer catálogo MCP: {e}. Usando catálogo por defecto.")
            self.servers = dict(OFFICIAL_MCP_SERVERS)

        return self.servers

    def save_registry(self) -> bool:
        """Guarda el catálogo de servidores MCP en formato JSON en disco."""
        try:
            with open(self.registry_path, "w", encoding="utf-8") as f:
                json.dump(self.servers, f, ensure_ascii=False, indent=2)
            logger.info(f"Catálogo MCP guardado correctamente en '{self.registry_path}'.")
            return True
        except Exception as e:
            logger.error(f"Error al guardar catálogo MCP: {e}")
            return False

    def register_server(
        self,
        server_id: str,
        name: str,
        command: str,
        args: Optional[List[str]] = None,
        transport_type: str = "stdio",
        sse_url: Optional[str] = None,
        env: Optional[Dict[str, str]] = None,
        description: str = "",
        roots: Optional[List[str]] = None,
        enabled: bool = True
    ) -> str:
        """
        Registra o actualiza un servidor MCP en el catálogo del proyecto.
        """
        server_id = server_id.lower().strip().replace(" ", "-")
        if not server_id:
            return "Error: El 'server_id' no puede estar vacío."

        entry = {
            "id": server_id,
            "name": name or server_id,
            "transport_type": transport_type.lower(),
            "command": command,
            "args": args or [],
            "sse_url": sse_url or "",
            "env": env or {},
            "description": description or f"Servidor MCP {name}",
            "enabled": enabled,
            "roots": roots or [self.base_dir]
        }

        self.servers[server_id] = entry
        self.save_registry()

        return (
            f"Éxito: Servidor MCP '{entry['name']}' ({server_id}) registrado correctamente.\\n"
            f"- Transporte: {entry['transport_type'].upper()}\\n"
            f"- Comando: {entry['command']} {' '.join(entry['args'])}\\n"
            f"- Roots: {', '.join(entry['roots'])}\""
        )

    def unregister_server(self, server_id: str) -> str:
        """Elimina un servidor del catálogo por su ID."""
        server_id = server_id.lower().strip()
        if server_id not in self.servers:
            return f"Error: El servidor MCP '{server_id}' no existe en el catálogo."

        info = self.servers.pop(server_id)
        self.save_registry()
        return f"Éxito: Servidor MCP '{info.get('name', server_id)}' eliminado del registro."

    def list_servers(self) -> str:
        """Muestra un resumen formateado de todos los servidores MCP en el catálogo."""
        if not self.servers:
            return "No hay servidores MCP registrados en el catálogo."

        lines = [f"🌐 CATÁLOGO DE SERVIDORES MCP ({len(self.servers)}):"]
        for sid, s in self.servers.items():
            status = "🟢 ACTIVO" if s.get("enabled", True) else "⚪ INACTIVO"
            cmd_info = f"{s.get('command', '')} {' '.join(s.get('args', []))}".strip()
            if s.get("transport_type") == "sse":
                cmd_info = s.get("sse_url", "URL SSE no especificada")

            lines.append(
                f"• [{sid}] {s.get('name', sid)} ({status})\\n"
                f"  Transporte: {s.get('transport_type', 'stdio').upper()} | Comando: {cmd_info}\\n"
                f"  Descripción: {s.get('description', 'N/A')}\\n"
                f"  Roots: {', '.join(s.get('roots', ['.']))}"
            )

        return "\n\n".join(lines)

    def get_server(self, server_id: str) -> Optional[Dict[str, Any]]:
        """Obtiene la configuración de un servidor por su ID o nombre."""
        server_id = server_id.lower().strip()
        if server_id in self.servers:
            return self.servers[server_id]

        for sid, data in self.servers.items():
            if data.get("name", "").lower() == server_id:
                return data

        return None
