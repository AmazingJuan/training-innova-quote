import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

const ringColor = { azul: 'stroke-azul-vivo fill-azul-vivo', cielo: 'stroke-cielo fill-cielo', blanco: 'stroke-white fill-white' } as const;

type RingProps = {
    /** Percentage of the circumference left open (the "C" of the logo, opening right). */
    gap?: number;
    tone?: keyof typeof ringColor;
    /** Dot on top of the ring, like the figure in the logo. */
    dot?: boolean;
    className?: string;
};

/** The open ring of the logo, as a bare SVG that fills its box. */
export function Ring({ gap = 14, tone = 'azul', dot = true, className }: RingProps) {
    return (
        <svg viewBox="0 0 100 100" className={cn('overflow-visible', ringColor[tone], className)} aria-hidden>
            <circle
                cx="50"
                cy="50"
                r="48.5"
                pathLength={100}
                strokeDasharray={`${100 - gap} ${gap}`}
                transform={`rotate(${(gap / 100) * 180} 50 50)`}
                fill="none"
                strokeWidth={1.3}
                strokeLinecap="round"
            />
            {dot && <circle cx="50" cy="1.5" r="2.4" stroke="none" />}
        </svg>
    );
}

type BrandRingProps = {
    src: string;
    alt: string;
    caption?: ReactNode;
    /** CSS object-position of the photo. */
    focus?: string;
    className?: string;
};

/** Real photograph cropped in a circle inside the brand ring. */
export function BrandRing({ src, alt, caption, focus = '50% 50%', className }: BrandRingProps) {
    return (
        <figure className={className}>
            <div className="relative aspect-square w-full">
                <div className="absolute inset-[5.5%] overflow-hidden rounded-full bg-nube">
                    <img src={src} alt={alt} className="h-full w-full object-cover" style={{ objectPosition: focus }} loading="eager" />
                </div>
                <Ring className="absolute inset-0 h-full w-full" />
            </div>
            {caption && <figcaption className="mt-4 text-sm text-pizarra">{caption}</figcaption>}
        </figure>
    );
}
