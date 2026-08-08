import CheckboxField from '@/schemas/fields/checkbox-field';
import RadioField from '@/schemas/fields/radio-field';
import SelectField from '@/schemas/fields/select-field';
import TextareaField from '@/schemas/fields/textarea-field';
import TextInputField from '@/schemas/fields/text-input-field';
import ToggleField from '@/schemas/fields/toggle-field';
import { registerField } from '@/schemas/registry';

/**
 * Register the default field types into the renderer registry. Called once
 * from the app entry point. Additional field types (and, later, plugin
 * components) register themselves the same way.
 */
export function registerDefaultFields(): void {
    registerField('text_input', TextInputField);
    registerField('select', SelectField);
    registerField('textarea', TextareaField);
    registerField('checkbox', CheckboxField);
    registerField('toggle', ToggleField);
    registerField('radio', RadioField);
}
