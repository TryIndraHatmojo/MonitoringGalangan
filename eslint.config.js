import js from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(js.configs.recommended, tseslint.configs.recommended, {
    files: ['resources/js/**/*.{ts,tsx}'],
    rules: { 'no-undef': 'off' },
});
