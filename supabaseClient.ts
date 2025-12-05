import { createClient, SupabaseClient } from '@supabase/supabase-js';

// Permite configurar por variables de entorno (Vite) o por ventana global (env.js)
const url = (typeof window !== 'undefined' && (window as any).__ENV?.SUPABASE_URL)
	|| (import.meta as any)?.env?.VITE_SUPABASE_URL;
const anonKey = (typeof window !== 'undefined' && (window as any).__ENV?.SUPABASE_ANON_KEY)
	|| (import.meta as any)?.env?.VITE_SUPABASE_ANON_KEY;

if (!url || !anonKey) {
	// No lanzar error en tiempo de construcción; el JS utilizará env.js en producción
	console.warn('Supabase URL o ANON KEY no configurados aún. Configure env.js o VITE_SUPABASE_*');
}

const supabase: SupabaseClient = createClient(url || '', anonKey || '');
export default supabase;
