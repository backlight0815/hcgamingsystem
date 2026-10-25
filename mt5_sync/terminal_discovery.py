"""Discover possible MT5 terminal paths for local PC or Windows VPS."""

from __future__ import annotations

from pathlib import Path


COMMON_TERMINAL_PATHS = (
    r"C:\Program Files\MetaTrader 5\terminal64.exe",
    r"C:\Program Files\PU Prime MetaTrader 5\terminal64.exe",
    r"C:\Program Files\PU Prime MT5 Terminal\terminal64.exe",
    r"C:\Program Files\MetaTrader 5 Terminal\terminal64.exe",
    r"C:\Program Files\HF Markets MT5 Terminal\terminal64.exe",
    r"C:\Program Files\HFM MetaTrader 5\terminal64.exe",
    r"C:\Program Files (x86)\MetaTrader 5\terminal64.exe",
)


def terminal_path_candidates(
    *,
    manual_path: str = "",
    auto_discover: bool = True,
) -> list[str]:
    candidates: list[str] = []
    manual = manual_path.strip()
    if manual:
        candidates.append(manual)

    if auto_discover:
        candidates.extend(COMMON_TERMINAL_PATHS)
        candidates.extend(_glob_terminal_paths(Path(r"C:\Program Files")))
        candidates.extend(_glob_terminal_paths(Path(r"C:\Program Files (x86)")))

    return _dedupe(candidates)


def existing_terminal_paths(
    *,
    manual_path: str = "",
    auto_discover: bool = True,
) -> list[str]:
    return [
        path
        for path in terminal_path_candidates(
            manual_path=manual_path,
            auto_discover=auto_discover,
        )
        if Path(path).exists()
    ]


def _glob_terminal_paths(root: Path) -> list[str]:
    if not root.exists():
        return []
    paths: list[str] = []
    try:
        for path in root.glob("*MetaTrader*/terminal64.exe"):
            paths.append(str(path))
        for path in root.glob("*MT5*/terminal64.exe"):
            paths.append(str(path))
        for path in root.glob("*Markets*/terminal64.exe"):
            paths.append(str(path))
        for path in root.glob("*HFM*/terminal64.exe"):
            paths.append(str(path))
    except OSError:
        return []
    return paths


def _dedupe(paths: list[str]) -> list[str]:
    seen: set[str] = set()
    result: list[str] = []
    for path in paths:
        text = path.strip()
        if not text:
            continue
        key = text.lower()
        if key in seen:
            continue
        seen.add(key)
        result.append(text)
    return result
