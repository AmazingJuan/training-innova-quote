import { cn } from '@/lib/utils';
import { Eye, EyeOff } from 'lucide-react';
import { useState, type InputHTMLAttributes } from 'react';
import TextInput from './text-input';

type PasswordInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
    isFocused?: boolean;
    /** Classes for the field as a whole (the button is placed over it). */
    className?: string;
};

/**
 * A password field with a button to read what was typed, to check it before
 * sending. It starts hidden and goes back to hidden on every page, so a
 * password is never left on screen by accident.
 *
 * The id and the aria attributes reach the input itself (the admin's FormField
 * injects them), which is where the label and the error point.
 */
export function PasswordInput({ className, ...props }: PasswordInputProps) {
    const [visible, setVisible] = useState(false);
    const Icon = visible ? EyeOff : Eye;
    const action = visible ? 'Ocultar la contraseña' : 'Mostrar la contraseña';

    return (
        <div className={cn('relative', className)}>
            {/* Room on the right so the button never sits on top of the text. */}
            <TextInput {...props} type={visible ? 'text' : 'password'} className="w-full pr-14" />
            <button
                type="button"
                onClick={() => setVisible((shown) => !shown)}
                aria-label={action}
                aria-pressed={visible}
                title={action}
                className="absolute inset-y-[2px] right-[2px] flex w-12 items-center justify-center rounded-r-control text-pizarra hover:text-azul focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-[-3px] focus-visible:outline-azul-vivo"
            >
                <Icon className="h-5 w-5" aria-hidden />
            </button>
        </div>
    );
}
