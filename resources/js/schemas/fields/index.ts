import CheckboxField from '@/schemas/fields/checkbox-field';
import CheckboxListField from '@/schemas/fields/checkbox-list-field';
import ColorPickerField from '@/schemas/fields/color-picker-field';
import DateTimePickerField from '@/schemas/fields/date-time-picker-field';
import FileUploadField from '@/schemas/fields/file-upload-field';
import KeyValueField from '@/schemas/fields/key-value-field';
import RichEditorField from '@/schemas/fields/rich-editor-field';
import RadioField from '@/schemas/fields/radio-field';
import RepeaterField from '@/schemas/fields/repeater-field';
import SelectField from '@/schemas/fields/select-field';
import TagsInputField from '@/schemas/fields/tags-input-field';
import TextareaField from '@/schemas/fields/textarea-field';
import TextInputField from '@/schemas/fields/text-input-field';
import ToggleButtonsField from '@/schemas/fields/toggle-buttons-field';
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
    registerField('checkbox_list', CheckboxListField);
    registerField('repeater', RepeaterField);
    registerField('file_upload', FileUploadField);
    registerField('rich_editor', RichEditorField);
    registerField('date_time_picker', DateTimePickerField);
    registerField('tags_input', TagsInputField);
    registerField('toggle_buttons', ToggleButtonsField);
    registerField('color_picker', ColorPickerField);
    registerField('key_value', KeyValueField);
}
