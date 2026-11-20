import { useRef, useState } from 'react';
import { useLocation, useNavigate } from 'react-router';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { ApiError } from './api';
import { toast } from '../components/ui/Toast';

/**
 * The state of a form and its save: the values, setting one of them, and the server's field errors after a 422.
 * Field errors come keyed by the field's path ("lines.0.packets"), as Laravel sends them.
 */
export function useForm<T extends Record<string, unknown>>(initial: T) {
    const [values, setValues] = useState<T>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const set = <K extends keyof T>(key: K, value: T[K]) => {
        setValues((current) => ({ ...current, [key]: value }));
        setErrors((current) => {
            if (!(key as string in current)) return current;
            const next = { ...current };
            delete next[key as string];
            return next;
        });
    };

    const fail = (error: unknown) => {
        if (error instanceof ApiError && error.status === 422) {
            setErrors(Object.fromEntries(Object.entries(error.errors).map(([field, messages]) => [field, messages[0]])));
            toast.error(error.first() ?? error.message);
            return;
        }
        toast.error(error instanceof Error ? error.message : String(error));
    };

    return { values, setValues, set, errors, setErrors, fail, reset: () => setValues(initial) };
}

/**
 * A save to the API that refreshes the lists and records it touched, says it worked, and shows the server's errors.
 */
export function useSave<TBody, TResult>(save: (body: TBody) => Promise<TResult>, options: { invalidate?: string[]; success?: string; onSuccess?: (result: TResult) => void; onError?: (error: unknown) => void } = {}) {
    const client = useQueryClient();
    return useMutation({
        mutationFn: save,
        onSuccess: (result) => {
            for (const key of options.invalidate ?? []) client.invalidateQueries({ predicate: (query) => query.queryKey.some((part) => typeof part === 'string' && part.startsWith(key)) });
            if (options.success) toast.success(options.success);
            options.onSuccess?.(result);
        },
        onError: (error) => {
            if (options.onError) options.onError(error);
            else toast.error(error instanceof ApiError ? (error.first() ?? error.message) : String(error));
        },
    });
}

/**
 * "Create & create another" on a create form: the button marks the save, and after it the form starts again empty instead
 * of going to the new record. A create page opens again fresh at its own path; a modal passes its own way to start again.
 */
export function useCreateAnother() {
    const navigate = useNavigate();
    const location = useLocation();
    const asked = useRef(false);

    return {
        /** Marks the coming save: true from the "create another" button, false from the plain create button. */
        ask: (another: boolean) => {
            asked.current = another;
        },
        /** After a create: starts again when "create another" was pressed, otherwise runs `then`. */
        after: (then: () => void, again?: () => void) => {
            const another = asked.current;
            asked.current = false;
            if (!another) {
                then();
            } else if (again) {
                again();
            } else {
                navigate(`${location.pathname}${location.search}`, { replace: true });
                window.scrollTo(0, 0);
            }
        },
    };
}

export type CreateAnother = ReturnType<typeof useCreateAnother>;
