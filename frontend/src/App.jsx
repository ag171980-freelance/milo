import { Routes, Route } from 'react-router-dom';
import Home from './pages/Home.jsx';
import ShopPage from './pages/shop/ShopPage.jsx';
import OrderStatus from './pages/shop/OrderStatus.jsx';
import AdminApp from './pages/admin/AdminApp.jsx';
import Terminos from './pages/legal/Terminos.jsx';
import Privacidad from './pages/legal/Privacidad.jsx';
import Comercios from './pages/legal/Comercios.jsx';
import Arrepentimiento from './pages/legal/Arrepentimiento.jsx';
import NotFound from './pages/NotFound.jsx';

// Las rutas fijas van antes que /:slug. Los slugs que coinciden con estas
// palabras están reservados en backend/tools/nuevo-local.php.
export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/admin/*" element={<AdminApp />} />
      <Route path="/terminos" element={<Terminos />} />
      <Route path="/privacidad" element={<Privacidad />} />
      <Route path="/comercios" element={<Comercios />} />
      <Route path="/arrepentimiento" element={<Arrepentimiento />} />
      <Route path="/:slug" element={<ShopPage />} />
      <Route path="/:slug/pedido/:publicId" element={<OrderStatus />} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  );
}
