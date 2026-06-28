-- 20260628000001_unified_credentials.sql
-- Goal: Generalize token storage to support multiple providers (TrueLayer, Plaid)

-- 1. Rename existing table
ALTER TABLE user_tokens RENAME TO user_credentials;

-- 2. Add provider column
ALTER TABLE user_credentials ADD COLUMN provider TEXT NOT NULL DEFAULT 'truelayer';

-- 3. Update Primary Key to support multiple providers per user
-- We drop the existing PK (which was just user_id)
ALTER TABLE user_credentials DROP CONSTRAINT user_tokens_pkey;
ALTER TABLE user_credentials ADD PRIMARY KEY (user_id, provider);

-- 4. Re-apply RLS (Policies should survive the rename, but we verify)
ALTER TABLE user_credentials ENABLE ROW LEVEL SECURITY;

DO $$ 
BEGIN
    -- Drop old policy if it didn't rename automatically or for clarity
    DROP POLICY IF EXISTS "Users can only manage their own tokens" ON user_credentials;
    
    CREATE POLICY "Users can manage their own credentials" 
    ON user_credentials FOR ALL USING (auth.uid() = user_id);
END $$;
