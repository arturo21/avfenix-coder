# -*- coding: utf-8 -*-
"""
session_manager.py - Gestor de Memoria y Persistencia de Sesión para AVFenix Coder (v2).
Maneja la lectura, escritura atómica con copia segura y restauración resiliente
de pestañas, historiales de chat e historial de prompts en formato JSON local.
"""

import os
import json
import copy
import logging
from typing import Dict, Any, Optional, List

logger = logging.getLogger("SessionManager")
DEFAULT_SESSION_FILE = "avfenix_session.json"

class SessionManager:
    """
    Administra la persistencia de datos de sesión de AVFenix Coder.
    """

    def __init__(self, filepath: str = DEFAULT_SESSION_FILE):
        self.filepath = filepath

    def save_session(
        self,
        conversations: Dict[str, Dict[str, Any]],
        tab_counter: int,
        active_tab_id: Optional[str] = None,
        prompt_history: Optional[List[str]] = None
    ) -> bool:
        """
        Guarda el estado completo de la TUI en un archivo JSON atómico con copia segura anti-concurrencia.
        """
        try:
            safe_conversations = copy.deepcopy(conversations)
            safe_history = copy.deepcopy(prompt_history or [])
        except Exception:
            safe_conversations = conversations
            safe_history = prompt_history or []

        data = {
            "version": "1.0",
            "tab_counter": tab_counter,
            "active_tab_id": active_tab_id or "tab-1",
            "prompt_history": safe_history,
            "conversations": safe_conversations
        }

        try:
            temp_file = f"{self.filepath}.tmp"
            with open(temp_file, "w", encoding="utf-8") as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            os.replace(temp_file, self.filepath)
            logger.debug(f"Sesión guardada exitosamente en '{self.filepath}'.")
            return True
        except Exception as e:
            logger.error(f"Error al guardar sesión en '{self.filepath}': {e}")
            return False

    def load_session(self) -> Optional[Dict[str, Any]]:
        """
        Carga y valida el archivo de sesión JSON si existe.
        """
        if not os.path.exists(self.filepath):
            logger.info(f"No se encontró archivo de sesión en '{self.filepath}'. Se iniciará sesión limpia.")
            return None

        try:
            with open(self.filepath, "r", encoding="utf-8") as f:
                data = json.load(f)

            if not isinstance(data, dict) or "conversations" not in data:
                logger.warning("Estructura de archivo de sesión no válida. Ignorando.")
                return None

            if not isinstance(data.get("conversations"), dict):
                logger.warning("Campo 'conversations' no es un diccionario. Ignorando.")
                return None

            logger.info(f"Sesión cargada correctamente desde '{self.filepath}'.")
            return data
        except Exception as e:
            logger.error(f"Error al leer archivo de sesión '{self.filepath}': {e}")
            return None

    def clear_session(self) -> bool:
        """Elimina el archivo de sesión activo."""
        try:
            if os.path.exists(self.filepath):
                os.remove(self.filepath)
                logger.info(f"Archivo de sesión '{self.filepath}' eliminado.")
            return True
        except Exception as e:
            logger.error(f"Error al eliminar archivo de sesión: {e}")
            return False
