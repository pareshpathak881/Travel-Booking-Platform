import './index.css';
import { createRoot } from 'react-dom/client';
import NavBar from './components/NavBar.jsx';

const root = document.getElementById('nav-root');
if (root) {
  createRoot(root).render(<NavBar />);
}
