import { router } from '@inertiajs/react';
import type { Errors, FormDataConvertible, VisitHelperOptions } from '@inertiajs/core';
import { zodResolver } from '@hookform/resolvers/zod';
import { useId, useRef, useState } from 'react';
import { useForm, type DefaultValues, type FieldPath, type FieldPathValue } from 'react-hook-form';
import type { ZodType } from 'zod';

type Payload = Record<string, FormDataConvertible>;
type FormVisitOptions = VisitHelperOptions<Payload>;

function messages(errors: Record<string, unknown>, prefix = ''): Record<string, string> {
    return Object.fromEntries(Object.entries(errors).flatMap(([key, value]) => {
        if (!value || typeof value !== 'object') return [];
        const path = prefix + key;
        if ('message' in value && typeof value.message === 'string') return [[path, value.message]];
        return Object.entries(messages(value as Record<string, unknown>, path + '.'));
    }));
}

export function useInertiaForm<T extends Record<string, unknown>>(defaults: T, schema: ZodType<T, T>) {
    const bag = useId();
    const initial = useRef(defaults);
    const form = useForm<T>({
        defaultValues: defaults as DefaultValues<T>,
        resolver: zodResolver(schema, undefined, { raw: true }),
    });
    const data = form.watch();
    const [processing, setProcessing] = useState(false);
    const [serverErrors, setServerErrors] = useState<Errors>({});
    const { errors, isDirty } = form.formState;

    function setData<K extends Extract<keyof T, string>>(key: K, value: T[K]): void;
    function setData(values: T): void;
    function setData(keyOrValues: Extract<keyof T, string> | T, value?: T[keyof T]) {
        const values = typeof keyOrValues === 'string' ? { [keyOrValues]: value } : keyOrValues;
        for (const [key, value] of Object.entries(values)) {
            const path = key as FieldPath<T>;
            form.setValue(path, value as FieldPathValue<T, typeof path>, { shouldDirty: true });
        }
    }

    function reset(...keys: Extract<keyof T, string>[]) {
        if (!keys.length) {
            form.reset(initial.current);
            setServerErrors({});
            return;
        }
        for (const key of keys) setData(key, initial.current[key]);
    }

    function submit(method: 'post' | 'put' | 'patch' | 'get', url: string, options: FormVisitOptions = {}) {
        void form.handleSubmit((values) => {
            setServerErrors({});
            router[method](url, values as Payload, {
                ...options,
                errorBag: bag,
                onStart: (visit) => {
                    setProcessing(true);
                    options.onStart?.(visit);
                },
                onError: (errors) => {
                    setServerErrors(errors);
                    return options.onError?.(errors);
                },
                onFinish: (visit) => {
                    setProcessing(false);
                    options.onFinish?.(visit);
                },
            });
        })();
    }

    return {
        data, setData, reset, processing, isDirty,
        errors: { ...serverErrors, ...messages(errors) },
        setDefaults: () => {
            initial.current = form.getValues();
            form.reset(initial.current);
        },
        get: (url: string, options?: FormVisitOptions) => submit('get', url, options),
        post: (url: string, options?: FormVisitOptions) => submit('post', url, options),
        put: (url: string, options?: FormVisitOptions) => submit('put', url, options),
        patch: (url: string, options?: FormVisitOptions) => submit('patch', url, options),
    };
}
