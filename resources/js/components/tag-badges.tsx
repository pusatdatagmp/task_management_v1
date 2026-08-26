// ==========================================================
// MODUL       : tag-badges
// KLASIFIKASI : UI
// TUJUAN      : Render daftar tag Task sebagai badge warna kecil (permintaan Boss
//               2026-08-26) — presentational murni, dipakai di tasks/index.tsx,
//               tasks/all.tsx, tasks/my-tasks.tsx, tasks/show.tsx supaya markup
//               badge tidak diduplikasi 4x.
// DIPANGGIL   : tasks/index.tsx, tasks/all.tsx, tasks/my-tasks.tsx, tasks/show.tsx
// MEMANGGIL   : ui/badge
// DATA MASUK  : tags[] ({id,name,color}, biasanya eager-loaded dari Task::tags())
// DATA KELUAR : -
// RISIKO      : Warna teks SELALU putih tetap (pola sama badge TaskStatus di
//               seluruh codebase) — lihat RISIKO tag-picker.tsx.
// ==========================================================

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface TagBadgesProps {
    tags: { id: number; name: string; color: string }[];
    className?: string;
}

export default function TagBadges({ tags, className }: TagBadgesProps) {
    if (tags.length === 0) return null;

    return (
        <div className={cn('flex flex-wrap gap-1', className)}>
            {tags.map((tag) => (
                <Badge
                    key={tag.id}
                    style={{ backgroundColor: tag.color, color: '#fff', borderColor: 'transparent' }}
                    className="px-1.5 py-0 text-[10px]"
                >
                    {tag.name}
                </Badge>
            ))}
        </div>
    );
}
