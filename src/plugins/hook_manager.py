# -*- coding: utf-8 -*-
"""
hook_manager.py - Bus de Eventos y Ganchos (Hooks) para AVFenix Coder.
"""

import logging
from typing import Dict, Any, List, Callable

logger = logging.getLogger("HookManager")


class HookManager:
    """Administra el registro y despacho asíncrono/síncrono de eventos."""
    def __init__(self):
        self._hooks: Dict[str, List[Callable]] = {}

    def register_hook(self, event_name: str, handler: Callable):
        if event_name not in self._hooks:
            self._hooks[event_name] = []
        self._hooks[event_name].append(handler)
        logger.info(f"Hook '{event_name}' registrado exitosamente.")

    def unregister_hook(self, event_name: str, handler: Callable):
        if event_name in self._hooks and handler in self._hooks[event_name]:
            self._hooks[event_name].remove(handler)

    def trigger(self, event_name: str, *args, **kwargs) -> List[Any]:
        results = []
        if event_name in self._hooks:
            for handler in self._hooks[event_name]:
                try:
                    res = handler(*args, **kwargs)
                    results.append(res)
                except Exception as e:
                    logger.error(f"Error al ejecutar hook '{event_name}': {e}")
        return results
