-- 20260628000000_user_tokens.sql
-- Goal: Secure database-backed TrueLayer token storage with RLS

CREATE TABLE IF NOT EXISTS user_tokens (
    user_id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    encrypted_token_envelope TEXT NOT NULL, -- Libsodium base64(nonce . ciphertext)
    last_rotation TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Enable RLS
ALTER TABLE user_tokens ENABLE ROW LEVEL SECURITY;

-- Policy: Only the owner can manage their own tokens
DO $$ 
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE policyname = 'Users can only manage their own tokens' AND tablename = 'user_tokens') THEN
        CREATE POLICY "Users can only manage their own tokens" 
        ON user_tokens FOR ALL USING (auth.uid() = user_id);
    END IF;
END $$;
