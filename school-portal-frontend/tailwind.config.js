/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{js,jsx,ts,tsx}',
    '../resources/views/**/*.blade.php',
    '../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    '../storage/framework/views/*.php',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
};
