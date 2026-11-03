import { Modal } from '@/components/ui/modal';
import { answerError, datePairErrors, pathAnswers, visibleFields } from '@/features/quotation-wizard/answers';
import { EncuadreForm } from '@/features/quotation-wizard/encuadre-form';
import type { Answer, PublicCondition } from '@/features/quotation-wizard/types';
import { useMemo, useState } from 'react';
import type { TreeData } from './types';

type TestRunProps = { tree: TreeData; open: boolean; onOpenChange: (open: boolean) => void };

/** Fill the unsaved questionnaire exactly as the client will, with the same form. */
export function TestRun({ tree, open, onOpenChange }: TestRunProps) {
    const conditions = useMemo(() => Object.fromEntries(tree.conditions.map((c) => [c.id, c as PublicCondition])), [tree]);
    const [answers, setAnswers] = useState<Record<number, Answer>>({});
    const [checked, setChecked] = useState(false);

    const fields = useMemo(() => visibleFields(conditions, tree.initial_condition_id, answers), [conditions, tree.initial_condition_id, answers]);
    const errors = useMemo(() => {
        if (!checked) return {};
        const found: Record<number, string> = {};
        for (const { id } of fields) {
            const error = answerError(conditions[id], answers[id]);
            if (error) found[id] = error;
        }
        return { ...found, ...datePairErrors(conditions, fields, answers) };
    }, [checked, fields, conditions, answers]);
    const result = pathAnswers(
        conditions,
        fields.map((f) => f.id),
        answers,
    );

    const change = (next: boolean) => {
        if (next) {
            setAnswers({});
            setChecked(false);
        }
        onOpenChange(next);
    };

    return (
        <Modal
            open={open}
            onOpenChange={change}
            title={`Prueba: ${tree.name}`}
            description="Así verá el cliente el formulario de solicitud. Nada de lo que responda aquí se guarda."
            className="max-w-3xl"
        >
            {tree.initial_condition_id === null ? (
                <p className="text-pizarra">Esta unidad aún no tiene preguntas.</p>
            ) : (
                <div className="space-y-6">
                    <EncuadreForm
                        conditions={conditions}
                        fields={fields}
                        answers={answers}
                        onAnswer={(id, answer) => setAnswers((current) => ({ ...current, [id]: answer }))}
                        errors={errors}
                    />

                    <div className="flex flex-wrap items-center gap-3">
                        <button type="button" onClick={() => setChecked(true)} className="btn btn-outline btn-sm">
                            Validar como al enviar
                        </button>
                        {checked && (
                            <span className="text-base text-pizarra">
                                {Object.keys(errors).length === 0 ? 'Todo listo para enviar.' : `${Object.keys(errors).length} campos por corregir.`}
                            </span>
                        )}
                    </div>

                    {Object.keys(result).length > 0 && (
                        <div>
                            <p className="font-display font-bold text-azul">Así quedarán las respuestas en la cotización</p>
                            <dl className="mt-2 divide-y divide-linea rounded-control border-2 border-linea">
                                {Object.entries(result).map(([question, answer]) => (
                                    <div key={question} className="px-4 py-3">
                                        <dt className="text-sm text-pizarra">{question}</dt>
                                        <dd className="whitespace-pre-line font-semibold">{answer}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </div>
            )}
        </Modal>
    );
}
