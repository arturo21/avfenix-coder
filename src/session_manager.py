# -*- coding: utf-8 -*-
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
            return True
        except Exception as e:
            logger.error(f"Error al guardar sesión: {e}")
            return False

    def load_session(self) -> Optional[Dict[str, Any]]:
        if not os.path.exists(self.filepath):
            return None

        try:
            with open(self.filepath, "r", encoding="utf-8") as f:
                data = json.load(f)

            if not isinstance(data, dict) or "conversations" not in data:
                return None

            if not isinstance(data.get("conversations"), dict):
                return None

            return data
        except Exception as e:
            logger.error(f"Error al leer sesión: {e}")
            return None

    def clear_session(self) -> bool:
        try:
            if os.path.exists(self.filepath):
                os.remove(self.filepath)
            return True
        except Exception as e:
            logger.error(f"Error al eliminar sesión: {e}")
            return False
