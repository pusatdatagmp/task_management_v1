import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { motion } from 'framer-motion';
import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primary/90',
                destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
                secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
                ghost: 'hover:bg-accent hover:text-accent-foreground',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 rounded-md px-3',
                lg: 'h-11 rounded-md px-8',
                icon: 'h-10 w-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

// SUMBER: Omit onDrag*/onAnimation* -- framer-motion mendefinisikan ulang
// signature event ini sendiri (gesture drag, BUKAN native HTML5 drag) dan
// TABRAKAN tipe dengan React.ButtonHTMLAttributes bawaan. Nol Button di app
// ini memakai native onDrag/onAnimationStart (drag task pakai @dnd-kit, beda
// mekanisme sama sekali) -- aman dibuang dari kontrak tipe.
export interface ButtonProps
    extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'onDrag' | 'onDragStart' | 'onDragEnd' | 'onAnimationStart' | 'onAnimationEnd'>,
        VariantProps<typeof buttonVariants> {
    asChild?: boolean;
}

// SUMBER: micro-interaction (permintaan Boss) -- tap/hover scale halus di
// SEMUA tombol app sekaligus (1 titik ubah, dampak luas). motion.create()
// (API framer-motion v11+, BUKAN motion() lama) supaya Slot (asChild, dipakai
// puluhan tempat utk bungkus <Link>) TETAP forward animasi ke elemen DOM anak
// sesungguhnya. Radix Dialog/Dropdown/Sheet TIDAK disentuh (animasi bawaan
// tailwindcss-animate tetap dipakai, di luar scope permintaan ini).
const MotionButton = motion.create('button');
const MotionSlot = motion.create(Slot);

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? MotionSlot : MotionButton;
    return (
        <Comp
            className={cn(buttonVariants({ variant, size, className }))}
            ref={ref}
            whileTap={{ scale: 0.97 }}
            whileHover={{ scale: 1.02 }}
            transition={{ duration: 0.1 }}
            {...props}
        />
    );
});
Button.displayName = 'Button';

export { Button, buttonVariants };
