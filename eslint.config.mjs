import { makeEslintConfig } from '@averay/codeformat';

export default [
  {
    ignores: ['node_modules', 'vendor'],
  },
  ...makeEslintConfig(),
];
