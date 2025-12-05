
import { createClient } from '@supabase/supabase-js';

// Permite configurar por archivo público /env.js (window.__ENV)
const supabaseUrl = (window.__ENV && window.__ENV.SUPABASE_URL) || '';
const supabaseKey = (window.__ENV && window.__ENV.SUPABASE_ANON_KEY) || '';

if (!supabaseUrl || !supabaseKey) {
	console.warn('Faltan SUPABASE_URL o SUPABASE_ANON_KEY. Cree /env.js basado en /env.example.js');
}

const supabase = createClient(supabaseUrl, supabaseKey);
export default supabase;