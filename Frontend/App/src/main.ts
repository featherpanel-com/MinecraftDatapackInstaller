import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'

const app = createApp(App)

app.use(router)

// Enable dark mode by default
document.documentElement.classList.add('dark')

app.mount('#app')
