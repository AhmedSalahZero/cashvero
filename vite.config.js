import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [
    laravel({
      input: [
	],
      refresh: true,
    }),
    vue(),
	
  ],
  optimizeDeps: {
	include: [
		'@univerjs/core',
		'@univerjs/sheets',
		'@univerjs/sheets-ui',
		'@univerjs/ui',
		'@univerjs/engine-formula',
		'@univerjs/sheets-formula',
		
	],
	// مهم جداً بدونه Vite ممكن يتعطل
	exclude: [],
},
resolve: {
	alias: {
		'react': 'react',
		'react-dom': 'react-dom',
	}
}

  
})
