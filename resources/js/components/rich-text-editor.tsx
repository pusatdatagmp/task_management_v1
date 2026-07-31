// ==========================================================
// MODUL       : rich-text-editor
// KLASIFIKASI : UI
// TUJUAN      : Editor description task (F-30). Headless Tiptap + toolbar Tailwind
//               tipis — TIDAK pakai CSS bawaan Tiptap supaya konsisten dengan
//               styling shadcn/ui yang sudah ada di proyek ini.
// DIPANGGIL   : tasks/create.tsx, tasks/edit.tsx
// MEMANGGIL   : @tiptap/react, @tiptap/starter-kit
// DATA MASUK  : value (HTML string dari form state)
// DATA KELUAR : onChange(html) — dipanggil tiap edit, disimpan sebagai tasks.description (longtext HTML)
// RISIKO      : editor.getHTML() dari StarterKit kosong = '<p></p>', BUKAN string
//               kosong — form yang butuh "description opsional benar-benar kosong"
//               harus normalisasi ini sendiri kalau perlu (lihat StoreTaskRequest).
// ==========================================================

import { Bold, Italic, List, ListOrdered } from 'lucide-react';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect } from 'react';

import { cn } from '@/lib/utils';

interface RichTextEditorProps {
    value: string;
    onChange: (html: string) => void;
    id?: string;
}

export default function RichTextEditor({ value, onChange, id }: RichTextEditorProps) {
    const editor = useEditor({
        extensions: [StarterKit],
        content: value,
        editorProps: {
            attributes: {
                id: id ?? '',
                class: 'min-h-[120px] w-full rounded-b-md bg-background px-3 py-2 text-sm focus-visible:outline-hidden [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5',
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    // SUMBER: form Edit Task me-load description existing SETELAH editor pertama
    // kali dibuat (Inertia props datang lewat props, bukan re-mount) — tanpa efek
    // ini, editor tetap kosong walau `value` prop sudah terisi dari server.
    useEffect(() => {
        if (editor && value !== editor.getHTML()) {
            editor.commands.setContent(value, { emitUpdate: false });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editor]);

    if (!editor) {
        return null;
    }

    const toolbarButton = (label: string, active: boolean, onClick: () => void, Icon: typeof Bold) => (
        <button
            type="button"
            aria-label={label}
            onClick={onClick}
            className={cn(
                'inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                active && 'bg-accent text-accent-foreground',
            )}
        >
            <Icon className="h-4 w-4" />
        </button>
    );

    return (
        <div className="rounded-md border border-input">
            <div className="flex items-center gap-1 border-b border-input bg-muted/30 p-1">
                {toolbarButton('Bold', editor.isActive('bold'), () => editor.chain().focus().toggleBold().run(), Bold)}
                {toolbarButton('Italic', editor.isActive('italic'), () => editor.chain().focus().toggleItalic().run(), Italic)}
                {toolbarButton('Bullet list', editor.isActive('bulletList'), () => editor.chain().focus().toggleBulletList().run(), List)}
                {toolbarButton(
                    'Numbered list',
                    editor.isActive('orderedList'),
                    () => editor.chain().focus().toggleOrderedList().run(),
                    ListOrdered,
                )}
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
