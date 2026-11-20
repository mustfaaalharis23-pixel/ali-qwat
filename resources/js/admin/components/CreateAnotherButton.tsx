import type { CreateAnother } from '../lib/form';
import { useT } from '../lib/i18n';
import { Button } from './ui';

/**
 * The "create & create another" button beside a create button. Inside a form it submits the form; elsewhere
 * (a modal's footer, the checkout) it runs `onClick`, which saves.
 */
export function CreateAnotherButton({ another, onClick, disabled, className }: { another: CreateAnother; onClick?: () => void; disabled?: boolean; className?: string }) {
    const t = useT();

    return (
        <Button
            className={className}
            disabled={disabled}
            onClick={(event) => {
                another.ask(true);
                if (onClick) onClick();
                else event.currentTarget.form?.requestSubmit();
            }}
        >
            {t('admin.actions.create_another')}
        </Button>
    );
}
