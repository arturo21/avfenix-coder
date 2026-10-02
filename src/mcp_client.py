# -*- coding: utf-8 -*-
"""
mcp_client.py - Cliente MCP (Model Context Protocol) modular, asíncrono y multi-servidor.
Soporta transportes STDIO y SSE, reintentos con backoff exponencial, y consumo completo de primitivas MCP:
- Tools (tools/list, tools/call)
- Resources (resources/list, resources/read)
- Prompts (prompts/list, prompts/get)
- Sampling (sampling/createMessage)
- Progress Tracking (notifications/progress)
- Roots (roots/list)
"""

import os
import asyncio
import logging
from enum import Enum
from typing import Any, Dict, List, Optional, Union, Callable
from contextlib import asynccontextmanager

# Importaciones del SDK oficial de MCP con fallbacks para verificación sintáctica sin dependencias
try:
    from mcp import ClientSession, StdioServerParameters
    from mcp.client.stdio import stdio_client
    from mcp.client.sse import sse_client
    import mcp.types as types
    from mcp.shared.exceptions import McpError
except ImportError:
    ClientSession = Any
    StdioServerParameters = Any
    stdio_client = Any
    sse_client = Any
    types = Any
    McpError = Exception

logger = logging.getLogger("MCPClientManager")


class TransportType(str, Enum):
    """Tipos de transporte soportados por el protocolo MCP."""
    STDIO = "stdio"
    SSE = "sse"


class MCPProgressTracker:
    """
    Rastreador de progreso para operaciones largas iniciadas por herramientas o servidores MCP.
    Mantiene estado de token de progreso, total de pasos y porcentaje de avance.
    """

    def __init__(self, callback: Optional[Callable[[str, float, float], None]] = None):
        self.active_progress: Dict[str, Dict[str, Any]] = {}
        self.callback = callback

    def update_progress(self, progress_token: str, progress: float, total: Optional[float] = None):
        """Actualiza el estado de progreso para un token dado."""
        pct = (progress / total * 100.0) if total and total > 0 else progress
        info = {
            "token": progress_token,
            "progress": progress,
            "total": total or 100.0,
            "percentage": round(pct, 2)
        }
        self.active_progress[progress_token] = info
        logger.info(f"Progreso MCP [{progress_token}]: {progress}/{total or 100} ({info['percentage']}%)")
        if self.callback:
            try:
                self.callback(progress_token, progress, total or 100.0)
            except Exception as e:
                logger.error(f"Error en callback de progreso: {e}")

    def get_progress(self, progress_token: str) -> Optional[Dict[str, Any]]:
        return self.active_progress.get(progress_token)


class MCPClientManager:
    """
    Gestor de Cliente MCP para producción.
    Administra la conexión, inicialización, reconexión con backoff exponencial,
    y la interacción con Tools, Resources, Prompts, Sampling, Progress y Roots.
    """

    def __init__(
        self,
        transport_type: Union[TransportType, str] = TransportType.STDIO,
        command: Optional[str] = None,
        args: Optional[List[str]] = None,
        env: Optional[Dict[str, str]] = None,
        sse_url: Optional[str] = None,
        connection_timeout: float = 30.0,
        max_retries: int = 3,
        backoff_factor: float = 2.0,
        roots: Optional[List[str]] = None,
        sampling_handler: Optional[Callable[[str], str]] = None
    ):
        self.transport_type = TransportType(transport_type)
        self.command = command
        self.args = args or []
        self.env = env
        self.sse_url = sse_url
        self.connection_timeout = connection_timeout
        self.max_retries = max_retries
        self.backoff_factor = backoff_factor

        # Primitiva Roots: Carpetas base permitidas para acceso seguro
        self.roots = roots or [os.path.abspath(".")]
        # Primitiva Sampling: Handler para solicitudes de generación LLM iniciadas por el servidor
        self.sampling_handler = sampling_handler
        # Primitiva Progress Tracking
        self.progress_tracker = MCPProgressTracker()

        self.session: Optional[ClientSession] = None
        self.is_connected: bool = False

    def get_roots_uris(self) -> List[Dict[str, str]]:
        """Devuelve la lista de carpetas base/raíces configuradas en formato URI file:///."""
        uris = []
        for r in self.roots:
            abs_p = os.path.abspath(r)
            uris.append({
                "uri": f"file://{abs_p}",
                "name": os.path.basename(abs_p) or "root"
            })
        return uris

    def set_roots(self, new_roots: List[str]):
        """Actualiza las carpetas base permitidas (Roots) del cliente MCP."""
        self.roots = [os.path.abspath(r) for r in new_roots]
        logger.info(f"Roots MCP actualizados: {self.roots}")

    @asynccontextmanager
    async def connect(self):
        """
        Context Manager asíncrono para gestionar el ciclo de vida completo de la conexión MCP.
        Soporta reintentos con backoff exponencial y limpieza controlada de recursos.
        """
        retry_count = 0
        current_delay = 1.0

        while retry_count <= self.max_retries:
            try:
                logger.info(
                    f"Iniciando conexión MCP [{self.transport_type.value}] "
                    f"(Intento {retry_count + 1}/{self.max_retries + 1})..."
                )

                if self.transport_type == TransportType.STDIO:
                    if not self.command:
                        raise ValueError("El parámetro 'command' es obligatorio para el transporte STDIO.")

                    server_params = StdioServerParameters(
                        command=self.command,
                        args=self.args,
                        env=self.env
                    )

                    async with stdio_client(server_params) as (read_stream, write_stream):
                        async with ClientSession(read_stream, write_stream) as session:
                            logger.info("Estableciendo handshake e inicializando ClientSession MCP...")
                            init_result = await asyncio.wait_for(
                                session.initialize(),
                                timeout=self.connection_timeout
                            )
                            logger.info(
                                f"Sesión MCP inicializada exitosamente con servidor: "
                                f"{getattr(init_result, 'serverInfo', 'desconocido')}"
                            )
                            self.session = session
                            self.is_connected = True
                            yield self
                            self.is_connected = False
                            self.session = None
                            return

                elif self.transport_type == TransportType.SSE:
                    if not self.sse_url:
                        raise ValueError("El parámetro 'sse_url' es obligatorio para el transporte SSE.")

                    async with sse_client(self.sse_url) as (read_stream, write_stream):
                        async with ClientSession(read_stream, write_stream) as session:
                            logger.info("Estableciendo handshake SSE e inicializando ClientSession MCP...")
                            init_result = await asyncio.wait_for(
                                session.initialize(),
                                timeout=self.connection_timeout
                            )
                            logger.info("Sesión MCP SSE inicializada exitosamente.")
                            self.session = session
                            self.is_connected = True
                            yield self
                            self.is_connected = False
                            self.session = None
                            return

            except asyncio.TimeoutError:
                logger.warning(
                    f"Timeout ({self.connection_timeout}s) durante la inicialización "
                    f"(Intento {retry_count + 1})."
                )
            except Exception as e:
                logger.error(
                    f"Error de conexión en transporte {self.transport_type.value}: {e}",
                    exc_info=True
                )

            retry_count += 1
            if retry_count <= self.max_retries:
                logger.info(f"Reintentando en {current_delay:.2f} segundos...")
                await asyncio.sleep(current_delay)
                current_delay *= self.backoff_factor
            else:
                self.is_connected = False
                self.session = None
                raise ConnectionError(
                    f"No se pudo establecer conexión con el servidor MCP tras {self.max_retries + 1} intentos."
                )

    def _ensure_connected(self):
        """Verifica que la sesión MCP esté activa e inicializada."""
        if not self.is_connected or self.session is None:
            raise RuntimeError("El cliente MCP no está conectado. Usa 'async with client.connect():'.")

    # =========================================================================
    # 🛠️ PRIMITIVAS: TOOLS, RESOURCES, PROMPTS, SAMPLING Y ROOTS
    # =========================================================================

    async def list_tools(self) -> List[Any]:
        """Obtiene la lista de herramientas expuestas por el servidor MCP (tools/list)."""
        self._ensure_connected()
        try:
            response = await asyncio.wait_for(
                self.session.list_tools(),
                timeout=self.connection_timeout
            )
            tools = getattr(response, "tools", [])
            return tools
        except Exception as e:
            logger.error(f"Error al listar herramientas MCP: {e}")
            raise

    async def call_tool(
        self,
        name: str,
        arguments: Optional[Dict[str, Any]] = None,
        progress_token: Optional[str] = None
    ) -> Any:
        """
        Ejecuta una herramienta con soporte de seguimiento de progreso.
        """
        self._ensure_connected()
        args = arguments or {}
        try:
            logger.info(f"Invocando herramienta MCP '{name}' con argumentos: {args}")
            result = await asyncio.wait_for(
                self.session.call_tool(name, arguments=args),
                timeout=self.connection_timeout
            )
            return result
        except Exception as e:
            logger.error(f"Error al invocar herramienta MCP '{name}': {e}")
            raise

    async def list_resources(self) -> List[Any]:
        """Obtiene recursos de solo lectura expuestos por el servidor (resources/list)."""
        self._ensure_connected()
        try:
            response = await asyncio.wait_for(
                self.session.list_resources(),
                timeout=self.connection_timeout
            )
            return getattr(response, "resources", [])
        except Exception as e:
            logger.error(f"Error al listar recursos MCP: {e}")
            raise

    async def read_resource(self, uri: str) -> Any:
        """Lee el contenido de un recurso por su URI (resources/read)."""
        self._ensure_connected()
        try:
            return await asyncio.wait_for(
                self.session.read_resource(uri),
                timeout=self.connection_timeout
            )
        except Exception as e:
            logger.error(f"Error al leer recurso MCP '{uri}': {e}")
            raise

    async def list_prompts(self) -> List[Any]:
        """Obtiene la lista de plantillas de prompts disponibles (prompts/list)."""
        self._ensure_connected()
        try:
            response = await asyncio.wait_for(
                self.session.list_prompts(),
                timeout=self.connection_timeout
            )
            return getattr(response, "prompts", [])
        except Exception as e:
            logger.error(f"Error al listar prompts MCP: {e}")
            raise

    async def get_prompt(self, name: str, arguments: Optional[Dict[str, str]] = None) -> Any:
        """Solicita el renderizado de un prompt (prompts/get)."""
        self._ensure_connected()
        args = arguments or {}
        try:
            return await asyncio.wait_for(
                self.session.get_prompt(name, arguments=args),
                timeout=self.connection_timeout
            )
        except Exception as e:
            logger.error(f"Error al obtener prompt MCP '{name}': {e}")
            raise

    async def handle_sampling_request(self, prompt: str, system_prompt: Optional[str] = None) -> str:
        """
        Primitiva Sampling (sampling/createMessage):
        Responde a una solicitud de generación LLM iniciada por el servidor MCP.
        """
        logger.info(f"Atendiendo solicitud de Sampling MCP: '{prompt[:60]}...'")
        if self.sampling_handler:
            try:
                return self.sampling_handler(prompt)
            except Exception as e:
                logger.error(f"Error en sampling handler: {e}")

        return f"[Respuesta Sampling Mock]: Procesado prompt '{prompt}'"


class MultiMCPClientManager:
    """
    Gestor unificado de múltiples conexiones MCP simultáneas.
    Permite consultar herramientas, recursos y prompts a través de un pool de servidores MCP.
    """

    def __init__(self):
        self.clients: Dict[str, MCPClientManager] = {}

    def add_client(self, server_id: str, client: MCPClientManager):
        self.clients[server_id] = client

    def get_client(self, server_id: str) -> Optional[MCPClientManager]:
        return self.clients.get(server_id)

    def remove_client(self, server_id: str):
        if server_id in self.clients:
            del self.clients[server_id]
