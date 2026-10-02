# -*- coding: utf-8 -*-
"""
plugin_manager.py - Carga Dinámica e Inspección de Plugins para AVFenix Coder.
"""

import os
import sys
import json
import importlib.util
import logging
from typing import Dict, Any, List, Callable
from src.plugins.hook_manager import HookManager

logger = logging.getLogger("PluginManager")
PLUGINS_DIR = os.path.abspath("plugins")


class PluginManager:
    """Maneja el ciclo de vida dinámico de los plugins instalados."""
    def __init__(self, plugins_directory: str = PLUGINS_DIR):
        self.plugins_dir = plugins_directory
        self.hook_manager = HookManager()
        self.active_plugins: Dict[str, Dict[str, Any]] = {}
        self.custom_xml_tools: Dict[str, Callable] = {}
        os.makedirs(self.plugins_dir, exist_ok=True)

    def load_all_plugins(self):
        """Descubre e inicializa todos los plugins válidos en el directorio de plugins."""
        if not os.path.exists(self.plugins_dir):
            return

        for item in os.listdir(self.plugins_dir):
            plugin_path = os.path.join(self.plugins_dir, item)
            if os.path.isdir(plugin_path):
                manifest_path = os.path.join(plugin_path, "manifest.json")
                if os.path.exists(manifest_path):
                    self.load_plugin(plugin_path)

    def load_plugin(self, plugin_dir: str) -> bool:
        """Carga dinámicamente un plugin en caliente desde su carpeta."""
        manifest_path = os.path.join(plugin_dir, "manifest.json")
        try:
            with open(manifest_path, "r", encoding="utf-8") as f:
                manifest = json.load(f)

            plugin_id = manifest.get("id")
            entrypoint = manifest.get("entrypoint", "main.py")
            entry_path = os.path.join(plugin_dir, entrypoint)

            if not os.path.exists(entry_path):
                logger.error(f"Punto de entrada '{entrypoint}' no encontrado en '{plugin_id}'.")
                return False

            module_name = f"plugin_{plugin_id.replace('-', '_')}"
            spec = importlib.util.spec_from_file_location(module_name, entry_path)
            if spec is None or spec.loader is None:
                return False

            module = importlib.util.module_from_spec(spec)
            sys.modules[module_name] = module
            spec.loader.exec_module(module)

            if hasattr(module, "setup"):
                module.setup(self.hook_manager, self)

            self.active_plugins[plugin_id] = {
                "manifest": manifest,
                "module": module,
                "path": plugin_dir,
                "enabled": True
            }
            logger.info(f"Plugin '{manifest.get('name')}' (v{manifest.get('version')}) cargado.")
            return True
        except Exception as e:
            logger.error(f"Error al cargar el plugin en '{plugin_dir}': {e}")
            return False

    def register_custom_tool(self, tag_name: str, handler: Callable):
        """Permite a las extensiones agregar nuevas herramientas XML al núcleo."""
        self.custom_xml_tools[tag_name] = handler
        logger.info(f"Herramienta XML personalizada '{tag_name}' registrada por plugin.")
