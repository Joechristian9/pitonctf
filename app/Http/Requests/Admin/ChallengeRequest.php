<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChallengeRequest extends FormRequest
{
    /**
     * Challenge files are served as-is from public/, so only types the web server will not execute.
     */
    public const FILE_EXTENSIONS = ['txt', 'log', 'html', 'htm', 'css', 'js', 'json', 'csv', 'md', 'sql', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'pdf', 'zip', 'pcap'];

    /**
     * Only admins reach this request; the admin route group checks the "admin" gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isCreating = $this->route('challenge') === null;

        return [
            'category' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'points' => ['required', 'integer', 'min:0', 'max:1000'],
            'description' => ['required', 'string', 'max:5000'],
            'hint' => ['nullable', 'string', 'max:2000'],
            // Required on create; left blank on edit it keeps the current flag.
            'flag' => [$isCreating ? 'required' : 'nullable', 'string', 'max:200'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'extensions:'.implode(',', self::FILE_EXTENSIONS)],
            'remove_files' => ['nullable', 'array'],
            'remove_files.*' => ['string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.*.extensions' => 'Challenge files must be one of: '.implode(', ', self::FILE_EXTENSIONS).'.',
            'files.*.max' => 'Each challenge file must be 10 MB or smaller.',
        ];
    }
}
