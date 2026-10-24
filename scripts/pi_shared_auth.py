"""DPAPI-protected, persistent Windows user login shared by local PI test phases."""
from __future__ import annotations
import base64
import ctypes
from ctypes import wintypes
import json
import os
from pathlib import Path

AUTH_FILE = Path(os.environ.get('LOCALAPPDATA', str(Path.home()))) / 'PlanningIntelligence' / 'LocalDemo' / 'pi-local-login.dpapi.json'

class _DataBlob(ctypes.Structure):
    _fields_ = [('cbData', wintypes.DWORD), ('pbData', ctypes.POINTER(ctypes.c_ubyte))]

def _unprotect(ciphertext: bytes) -> bytes:
    if os.name != 'nt':
        raise RuntimeError('PI_SHARED_LOGIN_REQUIRES_WINDOWS')
    crypt = ctypes.WinDLL('crypt32', use_last_error=True)
    kernel = ctypes.WinDLL('kernel32', use_last_error=True)
    crypt.CryptUnprotectData.argtypes = [ctypes.POINTER(_DataBlob), ctypes.c_void_p,
                                         ctypes.POINTER(_DataBlob), ctypes.c_void_p,
                                         ctypes.c_void_p, wintypes.DWORD, ctypes.POINTER(_DataBlob)]
    crypt.CryptUnprotectData.restype = wintypes.BOOL
    kernel.LocalFree.argtypes = [ctypes.c_void_p]
    kernel.LocalFree.restype = ctypes.c_void_p
    raw = ctypes.create_string_buffer(ciphertext)
    inp = _DataBlob(len(ciphertext), ctypes.cast(raw, ctypes.POINTER(ctypes.c_ubyte)))
    out = _DataBlob()
    if not crypt.CryptUnprotectData(ctypes.byref(inp), None, None, None, None, 1, ctypes.byref(out)):
        raise OSError(ctypes.get_last_error(), 'PI_SHARED_LOGIN_DPAPI_DECRYPT_FAILED')
    try:
        return ctypes.string_at(out.pbData, out.cbData)
    finally:
        kernel.LocalFree(ctypes.cast(out.pbData, ctypes.c_void_p))

def load_credentials() -> tuple[str, str]:
    if not AUTH_FILE.is_file():
        raise RuntimeError('PI_SHARED_LOGIN_NOT_INITIALIZED: run PI_UNIFIED_LOCAL_LOGIN_20261025.py once')
    record = json.loads(AUTH_FILE.read_text(encoding='utf-8'))
    if record.get('schema') != 'PI_LOCAL_DPAPI_V1':
        raise RuntimeError('PI_SHARED_LOGIN_UNKNOWN_RECORD_VERSION')
    payload = json.loads(_unprotect(base64.b64decode(record['ciphertext'], validate=True)))
    user, password = payload['username'], payload['password']
    if not isinstance(user, str) or not isinstance(password, str) or len(password) < 16:
        raise RuntimeError('PI_SHARED_LOGIN_CORRUPT')
    return user, password
