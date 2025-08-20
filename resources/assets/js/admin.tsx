import '../scss/admin.scss';
import '../css/tailwind.css';

import { createRoot } from 'react-dom/client';
import SettingsPanel from './SettingsPanel';
import DashboardPanel from './DashboardPanel';

const dashboardRoot = document.getElementById('worddown-dashboard-root');
if (dashboardRoot) {
  createRoot(dashboardRoot).render(<DashboardPanel />);
}

const settingsRoot = document.getElementById('worddown-settings-root');
if (settingsRoot) {
  createRoot(settingsRoot).render(<SettingsPanel />);
} 