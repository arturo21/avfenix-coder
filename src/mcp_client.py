# -*- coding: utf-8 -*-
"""
MCPClientManager - Cliente MCP (Model Context Protocol) modular y asíncrono.
Soporta transportes STDIO y SSE, gestión de ciclo de vida con reintentos y backoff exponencial,
y consumo completo de primitivas MCP (Tools, Resources, Prompts).
"""

import asyncio
import logging
from enum import Enum
from typing import Any, Dict, List, Optional, Union
from contextlib import asynccontextmanager

# Importaciones del SDK oficial de MCP con fallbacks para verificación sintáctica
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

# Configuración del sistema de logging estructurado
logger = logging.getLogger("MCPClientManager")


class TransportType(str, Enum):
    """Tipos de transporte soportados por el protocolo MCP."""
    STDIO = "stdio"
    SSE = "sse"


class MCPClientManager:
    """
    Gestor de Cliente MCP para producción.
    Administra la conexión, inicialización, reconexión con backoff exponencial,
    y la interacción con Tools, Resources y Prompts de cualquier servidor MCP.
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
    ):
        """
        Inicializa la configuración del cliente MCP.

        :param transport_type: Tipo de transporte (STDIO o SSE).
        :param command: Comando ejecutable para el servidor local (ej. 'python', 'node', 'uvx').
        :param args: Argumentos para el comando ejecutable.
        :param env: Variables de entorno adicionales para el proceso hijo.
        :param sse_url: URL remota para el transporte SSE (Server-Sent Events).
        :param connection_timeout: Tiempo límite en segundos para operaciones de red/conexión.
        :param max_retries: Número máximo de reintentos para la inicialización.
        :param backoff_factor: Factor multiplicador para el backoff exponencial entre reintentos.
        """
        self.transport_type = TransportType(transport_type)
        self.command = command
        self.args = args or []
        self.env = env
        self.sse_url = sse_url
        self.connection_timeout = connection_timeout
        self.max_retries = max_retries
        self.backoff_factor = backoff_factor

        self.session: Optional[ClientSession] = None
        self.is_connected: bool = False

    @asynccontextmanager
    async def connect(self):
        """
        Context Manager asíncrono para gestionar el ciclo de vida completo de la conexión.
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
                                f"Sesión MCP inicializada exitosamente con el servidor: "
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
    # 🛠️ PRIMITIVA: HERRAMIENTAS (TOOLS)
    # =========================================================================

    async def list_tools(self) -> List[Any]:
        """
        Obtiene la lista de herramientas expuestas por el servidor MCP (tools/list).
        """
        self._ensure_connected()
        try:
            logger.debug("Solicitando lista de herramientas (tools/list)...")
            response = await asyncio.wait_for(
                self.session.list_tools(),
                timeout=self.connection_timeout
            )
            tools = getattr(response, "tools", [])
            logger.info(f"Se obtuvieron {len(tools)} herramientas del servidor.")
            return tools
        except McpError as e:
            logger.error(f"Error MCP al listar herramientas: {e}")
            raise
        except Exception as e:
            logger.error(f"Excepción inesperada al listar herramientas: {e}")
            raise

    async def call_tool(self, name: str, arguments: Optional[Dict[str, Any]] = None) -> Any:
        """
        Ejecuta una herramienta específica expuesta por el servidor MCP (tools/call).

        :param name: Nombre de la herramienta a invocar.
        :param arguments: Diccionario con los parámetros requeridos por la herramienta.
        """
        self._ensure_connected()
        args = arguments or {}
        try:
            logger.info(f"Invocando herramienta '{name}' con argumentos: {args}")
            result = await asyncio.wait_for(
                self.session.call_tool(name, arguments=args),
                timeout=self.connection_timeout
            )
            logger.info(f"Herramienta '{name}' ejecutada con éxito.")
            return result
        except McpError as e:
            logger.error(f"Error MCP durante la ejecución de la herramienta '{name}': {e}")
            raise
        except Exception as e:
            logger.error(f"Excepción general al invocar herramienta '{name}': {e}")
            raise

    # =========================================================================
    # 📚 PRIMITIVA: RECURSOS (RESOURCES)
    # =========================================================================

    async def list_resources(self) -> List[Any]:
        """
        Obtiene la lista de recursos de solo lectura expuestos por el servidor (resources/list).
        """
        self._ensure_connected()
        try:
            logger.debug("Solicitando lista de recursos (resources/list)...")
            response = await asyncio.wait_for(
                self.session.list_resources(),
                timeout=self.connection_timeout
            )
            resources = getattr(response, "resources", [])
            logger.info(f"Se descubrieron {len(resources)} recursos en el servidor.")
            return resources
        except McpError as e:
            logger.error(f"Error MCP al listar recursos: {e}")
            raise
        except Exception as e:
            logger.error(f"Error inesperado al listar recursos: {e}")
            raise

    async def read_resource(self, uri: str) -> Any:
        """
        Lee el contenido de un recurso específico identificado por su URI (resources/read).

        :param uri: URI del recurso a consultar (ej. 'file:///config.json').
        """
        self._ensure_connected()
        try:
            logger.info(f"Leyendo recurso con URI: {uri}")
            content = await asyncio.wait_for(
                self.session.read_resource(uri),
                timeout=self.connection_timeout
            )
            logger.info(f"Recurso '{uri}' leído correctamente.")
            return content
        except McpError as e:
            logger.error(f"Error MCP al leer el recurso '{uri}': {e}")
            raise
        except Exception as e:
            logger.error(f"Error general al leer recurso '{uri}': {e}")
            raise

    # =========================================================================
    # 💬 PRIMITIVA: PROMPTS (PLANTILLAS)
    # =========================================================================

    async def list_prompts(self) -> List[Any]:
        """
        Obtiene la lista de plantillas de prompts disponibles en el servidor (prompts/list).
        """
        self._ensure_connected()
        try:
            logger.debug("Solicitando lista de prompts (prompts/list)...")
            response = await asyncio.wait_for(
                self.session.list_prompts(),
                timeout=self.connection_timeout
            )
            prompts = getattr(response, "prompts", [])
            logger.info(f"Se encontraron {len(prompts)} plantillas de prompts.")
            return prompts
        except McpError as e:
            logger.error(f"Error MCP al listar plantillas de prompts: {e}")
            raise
        except Exception as e:
            logger.error(f"Excepción general al listar prompts: {e}")
            raise

    async def get_prompt(self, name: str, arguments: Optional[Dict[str, str]] = None) -> Any:
        """
        Solicita el renderizado de una plantilla de prompt con argumentos especificados (prompts/get).

        :param name: Nombre de la plantilla de prompt.
        :param arguments: Diccionario de argumentos de texto para rellenar la plantilla.
        """
        self._ensure_connected()
        args = arguments or {}
        try:
            logger.info(f"Solicitando renderizado de prompt '{name}' con argumentos: {args}")
            prompt_data = await asyncio.wait_for(
                self.session.get_prompt(name, arguments=args),
                timeout=self.connection_timeout
            )
            logger.info(f"Prompt '{name}' renderizado exitosamente.")
            return prompt_data
        except McpError as e:
            logger.error(f"Error MCP al solicitar el prompt '{name}': {e}")
            raise
        except Exception as e:
            logger.error(f"Excepción general al obtener prompt '{name}': {e}")
            raise
