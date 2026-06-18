// Centralized Supabase Client
// Include this after setting window.SUPABASE_URL and window.SUPABASE_ANON_KEY

if (!window.SUPABASE_URL || !window.SUPABASE_ANON_KEY) {
    console.error('Supabase credentials not found');
} else {
    window.supabaseClient = window.supabase.createClient(
        window.SUPABASE_URL, 
        window.SUPABASE_ANON_KEY
    );
}
