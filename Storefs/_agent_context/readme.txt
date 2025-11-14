==========================================================
Final Space — Agent Context Summary (AES Decryption Issue)
==========================================================

🧭 OVERVIEW
The Final Space system consists of two linked sites:

1. Store (2.final-space.com)
   - Customer-facing storefront.
   - Uses AES-sealed PHP/JS blobs (.aes) for secure code protection.
   - Runs manifest validation and decryption via sealed_loader.php.

2. License Server (licenses.final-space.com)
   - Admin backend and API issuer.
   - Generates and signs manifests (manifest.json + manifest.sig).
   - Sends them to the Store using /api/receive_manifest.php.
   - Holds master RSA keys and AES sealing logic.

Both are PHP 8.2 compatible and linked via RSA + AES encryption.

----------------------------------------------------------
⚙️ EXPECTED AES BEHAVIOR
----------------------------------------------------------
- License Server reseals protected files and produces a new manifest.
- The Store receives and verifies this manifest using the RSA public key.
- If valid, sealed_loader.php decrypts .aes files at runtime using keys/aes_secret.key.
- Any tampering, missing manifest, or invalid key = blocked execution.

----------------------------------------------------------
🚨 CURRENT PROBLEM
----------------------------------------------------------
- AES-protected files are not decrypting properly.
- Wrappers (e.g., get_download.php → get_download.php.aes) show blank page or 500 error.
- Logs mention:

  [FS-AES] Missing AES secret
  [FS-AES] Invalid manifest signature
  [FS-AES] AES key mismatch

- Manifest.json and manifest.sig exist but verification fails.
- Keys may be mismatched between License Server and Store.

----------------------------------------------------------
📂 FILES RELEVANT TO FIX
----------------------------------------------------------
Store (2.final-space.com):
    /protected/sealed_loader.php
    /protected/keys/aes_secret.key
    /protected/manifest.json
    /protected/manifest.sig
    /protected/keys/server_public.pem
    /api/receive_manifest.php
    /api/get_download.php
    /api/get_download.php.aes
    /inc/aes_helper.php

License Server (licenses.final-space.com):
    /admin/ajax_reseal.php
    /inc/aes_helper.php
    /api/verify_license.php
    /api/bind_domain.php
    /config.php

----------------------------------------------------------
🧠 WHAT THE AGENT MUST DO
----------------------------------------------------------
1. Inspect AES-related files on both Store and License Server.
2. Verify the existence and readability of /protected/keys/aes_secret.key.
3. Check sealed_loader.php for correct path resolution and decryption logic.
4. Confirm that receive_manifest.php correctly saves:
       manifest.json, manifest.sig, and server_public.pem.
5. Validate that the manifest signature matches the current License Server keys.
6. If keys mismatch, reseal and push a new manifest.
7. Ensure all AES errors are logged clearly to /data/receive_log.txt.
8. After repair, test that protected .aes files decrypt and run properly.

----------------------------------------------------------
✅ EXPECTED RESULT
----------------------------------------------------------
- All wrappers (like /api/get_download.php) run successfully.
- No more "Missing AES secret" or "Invalid manifest" errors.
- test_local_connection.php confirms connectivity OK.
- Both servers share matching manifest and key versions.
- AES sealing/decryption process is stable and reproducible.

==========================================================
Prepared for GPT-5 Codex agent repair task.
Author: Szabolcs Nagy
Date: Auto-generated context for AES system debugging.
==========================================================
