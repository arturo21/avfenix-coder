# -*- coding: utf-8 -*-
"""
package_manager.py - Gestor de Instalación de Paquetes .zip para AVFenix Coder.
"""

import os
import json
import shutil
import zipfile
import logging
from src.plugins.plugin_manager import PluginManager

logger = logging.getLogger("PackageManager")


class PackageManager:
    """Gestor encargado de la instalación, validación y desinstalación de paquetes .zip."""
    def __init__(self, plugin_manager: PluginManager):
        self.plugin_manager = plugin_manager

    def install_from_zip(self, zip_path: str) -> str:
        """Valida e instala una extensión empaquetada en formato .zip."""
        if not os.path.exists(zip_path):
            return f"Error: El archivo paquete '{zip_path}' no existe."

        if not zipfile.is_zipfile(zip_path):
            return f"Error: '{zip_path}' no es un archivo .zip válido."

        try:
            with zipfile.ZipFile(zip_path, 'r') as zip_ref:
                namelist = zip_ref.namelist()
                manifest_file = next((name for name in namelist if name.endswith("manifest.json")), None)
                
                if not manifest_file:
                    return "Error de Validación: El paquete .zip no contiene un archivo 'manifest.json'."

                manifest_data = json.loads(zip_ref.read(manifest_file).decode("utf-8"))
                plugin_id = manifest_data.get("id")
                
                if not plugin_id:
                    return "Error de Validación: El manifiesto no especifica un 'id' válido."

                target_dir = os.path.join(self.plugin_manager.plugins_dir, plugin_id)
                if os.path.exists(target_dir):
                    shutil.rmtree(target_dir)

                zip_ref.extractall(target_dir)

                sub_manifest = os.path.join(target_dir, manifest_file)
                if not os.path.exists(os.path.join(target_dir, "manifest.json")) and os.path.exists(sub_manifest):
                    sub_dir = os.path.dirname(sub_manifest)
                    for file_name in os.listdir(sub_dir):
                        shutil.move(os.path.join(sub_dir, file_name), target_dir)

            success = self.plugin_manager.load_plugin(target_dir)
            if success:
                return f"Éxito: Extensión '{manifest_data.get('name')}' (v{manifest_data.get('version')}) instalada y activada correctamente."
            return f"Advertencia: Extensión descomprimida en '{target_dir}', pero ocurrió un error al inicializarla."

        except Exception as e:
            return f"Error crítico al instalar el paquete: {e}"

    def uninstall_plugin(self, plugin_id: str) -> str:
        """Desinstala y elimina limpiamente un plugin de disco."""
        if plugin_id in self.plugin_manager.active_plugins:
            plugin_info = self.plugin_manager.active_plugins.pop(plugin_id)
            plugin_path = plugin_info["path"]
            try:
                if os.path.exists(plugin_path):
                    shutil.rmtree(plugin_path)
                return f"Éxito: Extensión '{plugin_id}' desinstalada y eliminada de disco."
            except Exception as e:
                return f"Error al eliminar archivos del plugin: {e}"
        return f"Error: No se encontró la extensión '{plugin_id}' activa."
