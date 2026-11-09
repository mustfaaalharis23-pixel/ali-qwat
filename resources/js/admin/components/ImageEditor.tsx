import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import Cropper, { type Area } from 'react-easy-crop';
import { Crop, FlipHorizontal2, FlipVertical2, ImageIcon, RotateCcw, RotateCw, SlidersHorizontal, Sparkles, Undo2, X } from 'lucide-react';
import { cn, number } from '../lib/format';
import { useT } from '../lib/i18n';
import { Button } from './ui';

/**
 * The image editor every upload of the application goes through: crop to a shape, zoom, turn by quarters, straighten,
 * flip, adjust the light and colors, or pick a ready look; then the picture is drawn once at a sensible size and
 * compressed, so the server keeps a clean, light file. The original is used as it is when the user prefers.
 */

export interface ImageEditorOptions {
    /** The shape the crop opens with: 'original', or a width / height ratio such as 1 for a square. */
    aspect?: 'original' | number;
    /** The longest side of the saved picture, in pixels. */
    maxSize?: number;
    /** Lock the shape (an item's picture is always square, for instance). */
    lockAspect?: boolean;
}

interface Adjustments {
    brightness: number;
    contrast: number;
    saturation: number;
    warmth: number;
    exposure: number;
}

const neutral: Adjustments = { brightness: 100, contrast: 100, saturation: 100, warmth: 0, exposure: 0 };

const looks: { key: string; adjustments: Adjustments; extra?: string }[] = [
    { key: 'original', adjustments: neutral },
    { key: 'vivid', adjustments: { brightness: 104, contrast: 112, saturation: 135, warmth: 0, exposure: 0 } },
    { key: 'bright', adjustments: { brightness: 112, contrast: 104, saturation: 108, warmth: 0, exposure: 8 } },
    { key: 'warm', adjustments: { brightness: 103, contrast: 104, saturation: 112, warmth: 18, exposure: 0 } },
    { key: 'cool', adjustments: { brightness: 102, contrast: 106, saturation: 95, warmth: -16, exposure: 0 } },
    { key: 'mono', adjustments: { brightness: 104, contrast: 115, saturation: 0, warmth: 0, exposure: 0 } },
    { key: 'document', adjustments: { brightness: 118, contrast: 145, saturation: 40, warmth: 0, exposure: 10 } },
];

/** The CSS (and canvas) filter of the adjustments. Warmth tints toward amber, or toward blue below zero. */
function filterOf(value: Adjustments): string {
    const brightness = (value.brightness + value.exposure) / 100;
    const warm = value.warmth > 0 ? ` sepia(${value.warmth / 100}) hue-rotate(${-value.warmth * 0.25}deg)` : value.warmth < 0 ? ` hue-rotate(${-value.warmth * 0.6}deg)` : '';
    return `brightness(${brightness}) contrast(${value.contrast / 100}) saturate(${value.saturation / 100})${warm}`;
}

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = src;
    });
}

const radians = (degrees: number) => (degrees * Math.PI) / 180;

function rotatedSize(width: number, height: number, rotation: number) {
    const angle = radians(rotation);
    return {
        width: Math.abs(Math.cos(angle) * width) + Math.abs(Math.sin(angle) * height),
        height: Math.abs(Math.sin(angle) * width) + Math.abs(Math.cos(angle) * height),
    };
}

/** Draws the edited picture: turned, flipped, filtered, cropped, scaled down to maxSize, and encoded. */
async function render(src: string, crop: Area, rotation: number, flip: { h: boolean; v: boolean }, filter: string, maxSize: number, type: string): Promise<Blob> {
    const image = await loadImage(src);
    const box = rotatedSize(image.width, image.height, rotation);
    const stage = document.createElement('canvas');
    stage.width = Math.round(box.width);
    stage.height = Math.round(box.height);
    const context = stage.getContext('2d')!;
    context.translate(stage.width / 2, stage.height / 2);
    context.rotate(radians(rotation));
    context.scale(flip.h ? -1 : 1, flip.v ? -1 : 1);
    context.translate(-image.width / 2, -image.height / 2);
    context.filter = filter;
    if (type === 'image/jpeg') {
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, image.width, image.height);
    }
    context.drawImage(image, 0, 0);

    const scale = Math.min(1, maxSize / Math.max(crop.width, crop.height));
    const output = document.createElement('canvas');
    output.width = Math.max(1, Math.round(crop.width * scale));
    output.height = Math.max(1, Math.round(crop.height * scale));
    const out = output.getContext('2d')!;
    out.imageSmoothingQuality = 'high';
    out.drawImage(stage, crop.x, crop.y, crop.width, crop.height, 0, 0, output.width, output.height);

    return new Promise((resolve, reject) => output.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('encode'))), type, 0.9));
}

function Slider({ label, value, min, max, step = 1, onChange, unit = '', center }: { label: ReactNode; value: number; min: number; max: number; step?: number; onChange: (value: number) => void; unit?: string; center?: number }) {
    const percent = ((value - min) / (max - min)) * 100;
    const origin = center === undefined ? 0 : ((center - min) / (max - min)) * 100;
    return (
        <label className="block">
            <span className="mb-2 flex items-center justify-between text-[12.5px]">
                <span className="font-medium text-white/80">{label}</span>
                <button type="button" onClick={() => onChange(center ?? min)} className="num rounded px-1 text-white/50 hover:bg-white/10 hover:text-white" title="↺">
                    {value > 0 && center !== undefined && center === 0 ? '+' : ''}
                    {number(value, 1)}
                    {unit}
                </button>
            </span>
            <span className="relative block h-5">
                <span className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full bg-white/15" />
                <span
                    className="absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-[oklch(0.72_0.15_266)]"
                    style={{ insetInlineStart: `${Math.min(origin, percent)}%`, width: `${Math.abs(percent - origin)}%` }}
                />
                <input
                    type="range"
                    min={min}
                    max={max}
                    step={step}
                    value={value}
                    onChange={(event) => onChange(Number(event.target.value))}
                    className="absolute inset-0 w-full cursor-pointer appearance-none bg-transparent [&::-moz-range-thumb]:size-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:bg-white [&::-webkit-slider-thumb]:size-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white [&::-webkit-slider-thumb]:shadow-[0_1px_4px_rgb(0_0_0/0.5)]"
                />
            </span>
        </label>
    );
}

function ToolButton({ onClick, active, label, children }: { onClick: () => void; active?: boolean; label: string; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            aria-label={label}
            className={cn('flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg text-[12.5px] font-medium transition-colors', active ? 'bg-white text-[oklch(0.2_0.02_266)]' : 'bg-white/8 text-white/80 hover:bg-white/15 hover:text-white')}
        >
            {children}
        </button>
    );
}

interface EditorProps {
    file: File;
    options: ImageEditorOptions;
    onDone: (file: File | null) => void;
}

function Editor({ file, options, onDone }: EditorProps) {
    const t = useT();
    const ref = useRef<HTMLDialogElement>(null);
    const src = useMemo(() => URL.createObjectURL(file), [file]);
    const [natural, setNatural] = useState<{ width: number; height: number } | null>(null);
    const [tab, setTab] = useState<'crop' | 'adjust' | 'looks'>('crop');
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [quarter, setQuarter] = useState(0);
    const [straighten, setStraighten] = useState(0);
    const [flip, setFlip] = useState({ h: false, v: false });
    const [aspectKey, setAspectKey] = useState<string>(options.aspect === undefined || options.aspect === 'original' ? 'original' : String(options.aspect));
    const [adjustments, setAdjustments] = useState<Adjustments>(neutral);
    const [look, setLook] = useState('original');
    const [area, setArea] = useState<Area | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        loadImage(src).then((image) => setNatural({ width: image.width, height: image.height }));
        return () => URL.revokeObjectURL(src);
    }, [src]);

    useEffect(() => {
        const dialog = ref.current;
        if (dialog && !dialog.open) {
            dialog.showModal();
            dialog.focus();
        }
    }, []);

    const rotation = quarter * 90 + straighten;
    const filter = filterOf(adjustments);
    const turned = quarter % 2 !== 0;
    const originalAspect = natural ? (turned ? natural.height / natural.width : natural.width / natural.height) : 1;
    const aspect = aspectKey === 'original' ? originalAspect : Number(aspectKey);
    const maxSize = options.maxSize ?? 1800;
    const outputType = file.type === 'image/png' ? 'image/png' : 'image/webp';
    const outputSize = area ? { width: Math.round(area.width * Math.min(1, maxSize / Math.max(area.width, area.height))), height: Math.round(area.height * Math.min(1, maxSize / Math.max(area.width, area.height))) } : null;

    const aspects = [
        { key: 'original', label: t('admin.image_editor.aspect_original') },
        { key: '1', label: '1:1' },
        { key: String(4 / 3), label: '4:3' },
        { key: String(3 / 4), label: '3:4' },
        { key: String(16 / 9), label: '16:9' },
        { key: String(9 / 16), label: '9:16' },
    ];

    const reset = () => {
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setQuarter(0);
        setStraighten(0);
        setFlip({ h: false, v: false });
        setAdjustments(neutral);
        setLook('original');
        if (!options.lockAspect) setAspectKey(options.aspect === undefined || options.aspect === 'original' ? 'original' : String(options.aspect));
    };

    const apply = useCallback(async () => {
        if (!area) return;
        setSaving(true);
        try {
            const blob = await render(src, area, rotation, flip, filter, maxSize, outputType);
            const extension = outputType === 'image/png' ? 'png' : 'webp';
            const name = `${file.name.replace(/\.[^.]+$/, '') || 'image'}.${extension}`;
            onDone(new File([blob], name, { type: outputType, lastModified: Date.now() }));
        } catch {
            onDone(file);
        } finally {
            setSaving(false);
        }
    }, [area, src, rotation, flip, filter, maxSize, outputType, file, onDone]);

    const flipScale = `scale(${zoom * (flip.h ? -1 : 1)}, ${zoom * (flip.v ? -1 : 1)})`;

    return (
        <dialog
            ref={ref}
            tabIndex={-1}
            onCancel={(event) => {
                event.preventDefault();
                onDone(null);
            }}
            className="m-auto h-[min(860px,calc(100dvh-2rem))] outline-none w-[min(1240px,calc(100%-2rem))] overflow-hidden rounded-2xl border border-white/10 bg-[oklch(0.17_0.012_266)] p-0 text-white shadow-[var(--shadow-pop)] backdrop:bg-black/60"
            dir={document.documentElement.dir}
        >
            <div className="flex h-full flex-col">
                <header className="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-white/10">
                            <ImageIcon className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <p className="text-[14px] font-semibold">{t('admin.image_editor.title')}</p>
                            <p className="truncate text-[12px] text-white/50">{file.name}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <button type="button" onClick={reset} className="flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-[12.5px] text-white/70 hover:bg-white/10 hover:text-white">
                            <Undo2 className="size-4" />
                            <span className="hidden sm:inline">{t('admin.image_editor.reset')}</span>
                        </button>
                        <button type="button" onClick={() => onDone(null)} className="rounded-lg p-1.5 text-white/60 hover:bg-white/10 hover:text-white" aria-label={t('admin.actions.cancel')}>
                            <X className="size-4.5" />
                        </button>
                    </div>
                </header>

                <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
                    <div className="relative min-h-[300px] flex-1 bg-[repeating-conic-gradient(oklch(0.21_0.01_266)_0_25%,oklch(0.19_0.01_266)_0_50%)] bg-[length:20px_20px]" dir="ltr">
                        {natural && (
                            <Cropper
                                image={src}
                                crop={crop}
                                zoom={zoom}
                                rotation={rotation}
                                aspect={aspect}
                                minZoom={1}
                                maxZoom={5}
                                zoomSpeed={0.15}
                                restrictPosition
                                showGrid
                                onCropChange={setCrop}
                                onZoomChange={setZoom}
                                onRotationChange={(value) => setStraighten(Math.max(-45, Math.min(45, value - quarter * 90)))}
                                onCropComplete={(_, pixels) => setArea(pixels)}
                                transform={`translate(${crop.x}px, ${crop.y}px) rotate(${rotation}deg) ${flipScale}`}
                                style={{
                                    mediaStyle: { filter },
                                    cropAreaStyle: { border: '1.5px solid rgb(255 255 255 / 0.9)', boxShadow: '0 0 0 9999em rgb(10 12 20 / 0.62)' },
                                }}
                            />
                        )}
                        {outputSize && (
                            <span className="num pointer-events-none absolute bottom-3 left-3 rounded-md bg-black/55 px-2 py-1 text-[11.5px] text-white/80 backdrop-blur">
                                {outputSize.width} × {outputSize.height} px · {outputType === 'image/png' ? 'PNG' : 'WebP'}
                            </span>
                        )}
                    </div>

                    <aside className="flex w-full shrink-0 flex-col border-white/10 max-lg:border-t lg:w-[320px] lg:border-s">
                        <div className="flex gap-1 p-3">
                            {(
                                [
                                    ['crop', Crop, t('admin.image_editor.tab_crop')],
                                    ['adjust', SlidersHorizontal, t('admin.image_editor.tab_adjust')],
                                    ['looks', Sparkles, t('admin.image_editor.tab_looks')],
                                ] as const
                            ).map(([key, Icon, label]) => (
                                <ToolButton key={key} active={tab === key} onClick={() => setTab(key)} label={label}>
                                    <Icon className="size-4" />
                                    {label}
                                </ToolButton>
                            ))}
                        </div>

                        <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-4 pb-4">
                            {tab === 'crop' && (
                                <>
                                    {!options.lockAspect && (
                                        <div>
                                            <p className="mb-2 text-[12px] font-semibold tracking-wide text-white/50">{t('admin.image_editor.aspect')}</p>
                                            <div className="grid grid-cols-3 gap-1.5">
                                                {aspects.map((item) => (
                                                    <button
                                                        key={item.key}
                                                        type="button"
                                                        onClick={() => setAspectKey(item.key)}
                                                        className={cn('h-8.5 rounded-lg text-[12.5px] font-medium transition-colors', aspectKey === item.key ? 'bg-white text-[oklch(0.2_0.02_266)]' : 'bg-white/8 text-white/75 hover:bg-white/15')}
                                                    >
                                                        {item.label}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    <div>
                                        <p className="mb-2 text-[12px] font-semibold tracking-wide text-white/50">{t('admin.image_editor.rotate_flip')}</p>
                                        <div className="flex gap-1.5">
                                            <ToolButton onClick={() => setQuarter((value) => (value + 3) % 4)} label={t('admin.image_editor.rotate_left')}>
                                                <RotateCcw className="size-4" />
                                            </ToolButton>
                                            <ToolButton onClick={() => setQuarter((value) => (value + 1) % 4)} label={t('admin.image_editor.rotate_right')}>
                                                <RotateCw className="size-4" />
                                            </ToolButton>
                                            <ToolButton active={flip.h} onClick={() => setFlip((value) => ({ ...value, h: !value.h }))} label={t('admin.image_editor.flip_horizontal')}>
                                                <FlipHorizontal2 className="size-4" />
                                            </ToolButton>
                                            <ToolButton active={flip.v} onClick={() => setFlip((value) => ({ ...value, v: !value.v }))} label={t('admin.image_editor.flip_vertical')}>
                                                <FlipVertical2 className="size-4" />
                                            </ToolButton>
                                        </div>
                                    </div>
                                    <Slider label={t('admin.image_editor.straighten')} value={straighten} min={-45} max={45} step={0.5} unit="°" center={0} onChange={setStraighten} />
                                    <Slider label={t('admin.image_editor.zoom')} value={zoom} min={1} max={5} step={0.01} unit="×" center={1} onChange={setZoom} />
                                </>
                            )}

                            {tab === 'adjust' && (
                                <>
                                    <Slider label={t('admin.image_editor.brightness')} value={adjustments.brightness - 100} min={-50} max={50} center={0} onChange={(value) => { setLook('custom'); setAdjustments((current) => ({ ...current, brightness: value + 100 })); }} />
                                    <Slider label={t('admin.image_editor.exposure')} value={adjustments.exposure} min={-30} max={30} center={0} onChange={(value) => { setLook('custom'); setAdjustments((current) => ({ ...current, exposure: value })); }} />
                                    <Slider label={t('admin.image_editor.contrast')} value={adjustments.contrast - 100} min={-50} max={60} center={0} onChange={(value) => { setLook('custom'); setAdjustments((current) => ({ ...current, contrast: value + 100 })); }} />
                                    <Slider label={t('admin.image_editor.saturation')} value={adjustments.saturation - 100} min={-100} max={80} center={0} onChange={(value) => { setLook('custom'); setAdjustments((current) => ({ ...current, saturation: value + 100 })); }} />
                                    <Slider label={t('admin.image_editor.warmth')} value={adjustments.warmth} min={-40} max={40} center={0} onChange={(value) => { setLook('custom'); setAdjustments((current) => ({ ...current, warmth: value })); }} />
                                </>
                            )}

                            {tab === 'looks' && (
                                <div className="grid grid-cols-2 gap-2.5">
                                    {looks.map((item) => (
                                        <button
                                            key={item.key}
                                            type="button"
                                            onClick={() => {
                                                setLook(item.key);
                                                setAdjustments(item.adjustments);
                                            }}
                                            className={cn('group overflow-hidden rounded-xl text-start ring-2 transition', look === item.key ? 'ring-[oklch(0.72_0.15_266)]' : 'ring-transparent hover:ring-white/30')}
                                        >
                                            <span className="block aspect-[4/3] overflow-hidden bg-black/40">
                                                <img src={src} alt="" className="size-full object-cover transition-transform group-hover:scale-105" style={{ filter: filterOf(item.adjustments) }} />
                                            </span>
                                            <span className="block bg-white/6 px-2.5 py-1.5 text-[12px] font-medium text-white/85">{t(`admin.image_editor.look_${item.key}`)}</span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        <footer className="flex flex-wrap items-center gap-2 border-t border-white/10 p-3">
                            <button type="button" onClick={() => onDone(file)} className="h-9 rounded-lg px-3 text-[13px] font-medium text-white/70 hover:bg-white/10 hover:text-white">
                                {t('admin.image_editor.use_original')}
                            </button>
                            <div className="flex-1" />
                            <Button variant="primary" loading={saving} disabled={!area} onClick={apply}>
                                {t('admin.image_editor.apply')}
                            </Button>
                        </footer>
                    </aside>
                </div>
            </div>
        </dialog>
    );
}

const EDITABLE = ['image/jpeg', 'image/png', 'image/webp'];

/** Whether the editor can open this file: a JPEG, PNG, or WebP picture. A PDF or anything else goes up as it is. */
export function isEditableImage(file: File): boolean {
    return EDITABLE.includes(file.type);
}

/**
 * The editor as a hook: const { edit, editAll, editor } = useImageEditor(); render {editor} once, then
 * const file = await edit(picked) gives the edited picture, the original ("use original"), or null when cancelled.
 * Files that are not pictures come back untouched.
 */
export function useImageEditor(defaults: ImageEditorOptions = {}) {
    const [current, setCurrent] = useState<{ file: File; options: ImageEditorOptions; resolve: (file: File | null) => void } | null>(null);

    const edit = useCallback(
        (file: File, options: ImageEditorOptions = {}) =>
            isEditableImage(file) ? new Promise<File | null>((resolve) => setCurrent({ file, options: { ...defaults, ...options }, resolve })) : Promise.resolve(file),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    /** Edits several pictures one after the other; a cancelled one is left out. */
    const editAll = useCallback(
        async (files: FileList | File[] | null, options: ImageEditorOptions = {}) => {
            const edited: File[] = [];
            for (const file of Array.from(files ?? [])) {
                const result = await edit(file, options);
                if (result) edited.push(result);
            }
            return edited;
        },
        [edit],
    );

    const editor = current ? (
        <Editor
            key={current.file.name + current.file.lastModified}
            file={current.file}
            options={current.options}
            onDone={(file) => {
                current.resolve(file);
                setCurrent(null);
            }}
        />
    ) : null;

    return { edit, editAll, editor };
}
