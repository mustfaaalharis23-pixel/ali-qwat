import { useState } from 'react';
import { Download } from 'lucide-react';
import { toQueryString } from '../lib/api';
import { useI18n } from '../lib/i18n';
import { Button, Modal } from './ui';

export function ExcelTemplateDownload({ endpoint, translationKey, small = false }: { endpoint: string; translationKey: string; small?: boolean }) {
    const { locale, locales, t } = useI18n();
    const [open, setOpen] = useState(false);
    const [selection, setSelection] = useState<string[] | null>(null);
    const selected = selection ?? [locale];
    const title = t(`${translationKey}.download_template`);

    const download = () => {
        window.location.assign(`/admin-api${endpoint}${toQueryString({ languages: selected })}`);
        setOpen(false);
    };

    return (
        <>
            <Button size={small ? 'sm' : 'md'} icon={<Download className="size-4" />} onClick={() => setOpen(true)}>
                {title}
            </Button>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                size="sm"
                title={title}
                description={t(`${translationKey}.template_languages`)}
                footer={
                    <>
                        <Button onClick={() => setOpen(false)}>{t('admin.actions.cancel')}</Button>
                        <Button variant="primary" icon={<Download className="size-4" />} disabled={selected.length === 0} onClick={download}>
                            {t(`${translationKey}.download_xlsx`)}
                        </Button>
                    </>
                }
            >
                <div className="space-y-2">
                    {Object.entries(locales).map(([language, name]) => (
                        <label key={language} className="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-3 py-2.5 text-sm font-medium">
                            <input
                                type="checkbox"
                                className="size-4 accent-accent"
                                checked={selected.includes(language)}
                                onChange={(event) => setSelection(event.target.checked ? [...selected, language] : selected.filter((choice) => choice !== language))}
                            />
                            <span lang={language.replace('_', '-')} dir="auto">{name}</span>
                        </label>
                    ))}
                </div>
            </Modal>
        </>
    );
}
