import { useEffect, useState } from 'react';
export const CART_KEY = 'madina-import:cart:v1';
export type CartItem = { id: number; quantity: number; name: string; price: number; image_url?: string | null };
export function readCart(): CartItem[] {
    try {
        const data = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
        return Array.isArray(data) ? data.filter(i => Number.isInteger(i.id) && Number.isInteger(i.quantity) && i.quantity > 0 && i.quantity <= 10000 && typeof i.name === 'string' && Number.isFinite(i.price)).slice(0,100) : [];
    } catch { return []; }
}
export function writeCart(items: CartItem[]) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
    window.dispatchEvent(new Event('madina-cart'));
}
export function addCart(product: Omit<CartItem, 'quantity'>) {
    const items = readCart(); const line = items.find(i => i.id === product.id);
    if (line) line.quantity = Math.min(10000, line.quantity + 1); else items.push({ ...product, quantity: 1 });
    writeCart(items);
}
export function useCart() {
    const [items, setItems] = useState<CartItem[]>(readCart);
    useEffect(() => { const sync = () => setItems(readCart()); window.addEventListener('madina-cart', sync); window.addEventListener('storage', sync); return () => { window.removeEventListener('madina-cart', sync); window.removeEventListener('storage', sync); }; }, []);
    return items;
}
export const money = (value: number) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(value) + ' Ar';
