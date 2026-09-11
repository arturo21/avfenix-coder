# -*- coding: utf-8 -*-
"""
main_mcp.py - Script de demostración de producción para el Cliente MCP.
Inicializa el cliente, se conecta a un servidor MCP local vía STDIO,
descubre y consume Tools, Resources y Prompts con manejo completo de errores y logging.
"""

import asyncio
import logging
import sys
from mcp_client import MCPClientManager, TransportType


def setup_logging():
    """Configura el sistema de logging estándar para consola."""
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
        handlers=[logging.StreamHandler(sys.stdout)]
    )


async def main():
    setup_logging()
    logger = logging.getLogger("MainMCP")

    logger.info("==================================================")
    logger.info("   DEMOSTRACIÓN DE CLIENTE MCP (Python SDK)      ")
    logger.info("==================================================")

    # Configuración del Cliente MCP para servidor local vía STDIO
    # Ejemplo: Conexión a un servidor de archivos o sqlite (p. ej. 'uvx mcp-server-sqlite' o script python)
    client = MCPClientManager(
        transport_type=TransportType.STDIO,
        command="python3",
        args=["-m", "mcp.server"],  # O la ruta a tu servidor MCP local
        connection_timeout=15.0,
        max_retries=2,
        backoff_factor=1.5
    )

    try:
        # Iniciar ciclo de vida de conexión asíncrona
        async with client.connect():
            logger.info("--- 1. DESCUBRIMIENTO DE HERRAMIENTAS (TOOLS) ---")
            tools = await client.list_tools()
            for t in tools:
                logger.info(f"  [Tool] {getattr(t, 'name', 'desconocido')}: {getattr(t, 'description', '')}")

            # Ejemplo de invocación de herramienta si existe alguna
            if tools:
                first_tool_name = getattr(tools[0], 'name', None)
                if first_tool_name:
                    logger.info(f"Ejecutando llamada de prueba a la herramienta '{first_tool_name}'...")
                    tool_result = await client.call_tool(first_tool_name, arguments={})
                    logger.info(f"Resultado de herramienta: {tool_result}")

            logger.info("--- 2. DESCUBRIMIENTO DE RECURSOS (RESOURCES) ---")
            resources = await client.list_resources()
            for r in resources:
                logger.info(f"  [Resource] {getattr(r, 'uri', 'desconocido')} - {getattr(r, 'name', '')}")

            # Ejemplo de lectura de recurso si existe alguno
            if resources:
                first_uri = getattr(resources[0], 'uri', None)
                if first_uri:
                    logger.info(f"Leyendo recurso de prueba '{first_uri}'...")
                    res_content = await client.read_resource(first_uri)
                    logger.info(f"Contenido del recurso: {res_content}")

            logger.info("--- 3. DESCUBRIMIENTO DE PROMPTS (PLANTILLAS) ---")
            prompts = await client.list_prompts()
            for p in prompts:
                logger.info(f"  [Prompt] {getattr(p, 'name', 'desconocido')}: {getattr(p, 'description', '')}")

            # Ejemplo de obtención de prompt si existe alguno
            if prompts:
                first_prompt_name = getattr(prompts[0], 'name', None)
                if first_prompt_name:
                    logger.info(f"Renderizando plantilla de prompt '{first_prompt_name}'...")
                    prompt_data = await client.get_prompt(first_prompt_name, arguments={})
                    logger.info(f"Prompt renderizado: {prompt_data}")

    except ConnectionError as e:
        logger.error(f"Error de conexión con el servidor MCP: {e}")
    except Exception as e:
        logger.error(f"Fallo durante la ejecución de la sesión MCP: {e}", exc_info=True)


if __name__ == "__main__":
    asyncio.run(main())
