// ==========================================================
// MODUL       : tag-picker
// KLASIFIKASI : UI
// TUJUAN      : Combobox multi-select tag (permintaan Boss 2026-08-26) — cari +
//               pilih dari katalog Tag organisasi, tag terpilih tampil sebagai
//               chip warna dengan tombol hapus. Dipakai tasks/create.tsx &
//               tasks/edit.tsx. Komponen presentational murni (controlled),
//               nol network call sendiri — parent yang pegang state `selected`.
//               REVISI 2026-08-27 (audit Boss): SEBELUMNYA daftar pilihan pakai
//               `cmdk` (ui/command.tsx) di dalam Popover — popover & daftar
//               tampil normal, TAPI item di dalamnya NOL respons pointer (hover
//               maupun klik), dibuktikan via tombol polos SEBELAH-nya (di
//               PopoverContent yang SAMA) yang BERFUNGSI normal — jadi akar
//               masalah terisolasi persis di `cmdk`, bukan di Popover. Search+
//               daftar SEKARANG HTML polos (input+button, filter di JS lokal)
//               di dalam Popover yang sama -- cmdk TIDAK dipakai lagi di sini
//               (ui/command.tsx tetap ada, F-16-style, nol pemanggil tersisa).
// DIPANGGIL   : tasks/create.tsx, tasks/edit.tsx
// MEMANGGIL   : ui/popover, ui/badge
// DATA MASUK  : tags[] (katalog tag organisasi, {id,name,color}), selected (id[])
// DATA KELUAR : onChange(id[]) -- parent yang simpan ke useForm state
// RISIKO      : Warna badge SELALU teks putih tetap (pola sama badge TaskStatus
//               di seluruh codebase) -- admin bertanggung jawab pilih warna cukup
//               gelap, TIDAK ADA kalkulasi kontras otomatis.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Check, Plus, X } from 'lucide-react';
import { useState } from 'react';

export interface TagOption {
    id: number;
    name: string;
    color: string;
}

interface TagPickerProps {
    tags: TagOption[];
    selected: number[];
    onChange: (ids: number[]) => void;
}

export default function TagPicker({ tags, selected, onChange }: TagPickerProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const selectedTags = tags.filter((tag) => selected.includes(tag.id));
    const filteredTags = tags.filter((tag) => tag.name.toLowerCase().includes(search.trim().toLowerCase()));

    const toggle = (id: number) => {
        onChange(selected.includes(id) ? selected.filter((s) => s !== id) : [...selected, id]);
    };

    return (
        <div className="flex flex-col gap-2">
            {selectedTags.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {selectedTags.map((tag) => (
                        <Badge key={tag.id} style={{ backgroundColor: tag.color, color: '#fff', borderColor: 'transparent' }} className="gap-1 pr-1">
                            {tag.name}
                            <button
                                type="button"
                                onClick={() => toggle(tag.id)}
                                aria-label={`Hapus tag ${tag.name}`}
                                className="rounded-full hover:bg-black/20"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button type="button" variant="outline" size="sm" className="w-fit gap-1">
                        <Plus className="h-3.5 w-3.5" />
                        Tambah tag...
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-64 p-0" align="start">
                    <div className="border-b p-2">
                        <input
                            type="text"
                            autoFocus
                            placeholder="Cari tag..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full bg-transparent text-sm outline-hidden"
                        />
                    </div>
                    <div className="max-h-64 overflow-y-auto p-1">
                        {filteredTags.length === 0 && (
                            <p className="text-muted-foreground p-3 text-center text-sm">
                                {tags.length === 0 ? 'Belum ada tag — buat dulu di Pengaturan > Setelan.' : 'Tidak ada tag ditemukan.'}
                            </p>
                        )}
                        {filteredTags.map((tag) => {
                            const isSelected = selected.includes(tag.id);

                            return (
                                <button
                                    key={tag.id}
                                    type="button"
                                    onClick={() => toggle(tag.id)}
                                    className="hover:bg-accent hover:text-accent-foreground flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm"
                                >
                                    <span className="h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: tag.color }} />
                                    <span className="flex-1">{tag.name}</span>
                                    {isSelected && <Check className="h-4 w-4" />}
                                </button>
                            );
                        })}
                    </div>
                </PopoverContent>
            </Popover>
        </div>
    );
}
