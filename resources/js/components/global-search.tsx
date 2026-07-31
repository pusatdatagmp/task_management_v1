/**
 * ==========================================================
 * MODUL       : global-search.tsx
 * KLASIFIKASI : UI
 * TUJUAN      : Search box FULLTEXT (F-7) di header, dipakai di semua halaman lewat
 *               AppSidebarHeader. Debounce 300ms supaya tidak nge-hit endpoint tiap
 *               keystroke.
 * DIPANGGIL   : app-sidebar-header.tsx
 * MEMANGGIL   : GET route('tasks.search') — JSON, bukan Inertia::render (03-BUSINESS-FLOW §10)
 * DATA MASUK  : Ketikan user
 * DATA KELUAR : Navigasi ke halaman detail task (tasks.show, F-82) saat hasil diklik
 * RISIKO      : F-34 — filter permission dikerjakan di BACKEND (TaskController::search()),
 *               komponen ini cuma menampilkan apa pun yang backend kembalikan.
 * ==========================================================
 */
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface SearchResult {
    id: number;
    project_id: number;
    title: string;
    project_name: string;
    status_name: string;
    status_color: string;
    snippet: string;
}

interface SearchResponse {
    message: string | null;
    results: SearchResult[];
}

export function GlobalSearch() {
    const [query, setQuery] = useState('');
    const [response, setResponse] = useState<SearchResponse | null>(null);
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    // BUSINESS RULE: debounce 300ms (B7) — tanpa ini tiap keystroke jadi 1 request.
    useEffect(() => {
        if (query.trim().length === 0) {
            setResponse(null);
            return;
        }

        const timeout = setTimeout(() => {
            fetch(`/search?q=${encodeURIComponent(query.trim())}`, {
                headers: { Accept: 'application/json' },
            })
                .then((res) => res.json())
                .then((data: SearchResponse) => setResponse(data));
        }, 300);

        return () => clearTimeout(timeout);
    }, [query]);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function goToTask(result: SearchResult) {
        setIsOpen(false);
        setQuery('');
        setResponse(null);
        router.visit(`/projects/${result.project_id}/tasks/${result.id}`);
    }

    const showPanel = isOpen && query.trim().length > 0;

    return (
        <div ref={containerRef} className="relative w-full max-w-sm">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <input
                    type="text"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onFocus={() => setIsOpen(true)}
                    placeholder="Cari task..."
                    className="h-9 w-full rounded-md border border-input bg-background py-2 pr-3 pl-9 text-sm placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring"
                />
            </div>

            {showPanel && (
                <div className="absolute top-full z-50 mt-1 w-full rounded-md border bg-popover shadow-md">
                    {response === null ? (
                        <div className="p-3 text-sm text-muted-foreground">Mencari...</div>
                    ) : response.message ? (
                        <div className="p-3 text-sm text-muted-foreground">{response.message}</div>
                    ) : response.results.length === 0 ? (
                        <div className="p-3 text-sm text-muted-foreground">Tidak ada task yang cocok dengan &apos;{query.trim()}&apos;.</div>
                    ) : (
                        <ul className="max-h-96 overflow-y-auto py-1">
                            {response.results.map((result) => (
                                <li key={result.id}>
                                    <button
                                        type="button"
                                        onClick={() => goToTask(result)}
                                        className="flex w-full flex-col gap-0.5 px-3 py-2 text-left text-sm hover:bg-accent"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-medium">{result.title}</span>
                                            <span
                                                className="rounded px-1.5 py-0.5 text-xs text-white"
                                                style={{ backgroundColor: result.status_color }}
                                            >
                                                {result.status_name}
                                            </span>
                                        </div>
                                        <span className="text-xs text-muted-foreground">{result.project_name}</span>
                                        {result.snippet && <span className="truncate text-xs text-muted-foreground">{result.snippet}</span>}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
