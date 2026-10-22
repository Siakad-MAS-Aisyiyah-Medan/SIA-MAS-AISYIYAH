import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.jsx'
import './mobile-responsive.css'

// Handle dynamic import errors (usually due to new deployment causing chunk hash changes)
window.addEventListener('vite:preloadError', (event) => {
  if (sessionStorage.getItem('chunk_error_reloaded')) return;
  sessionStorage.setItem('chunk_error_reloaded', 'true');
  event.preventDefault();
  window.location.reload();
});

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
