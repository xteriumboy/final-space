# 📄 Final Space — AES Single-File Stabilization Phase
*(Agent Instructions v1.0)*

------------------------------------------------------------
🧭 PROJECT CONTEXT

You are connected online to the **live Final Space ecosystem**:

- **Store (Frontend):** https://2.final-space.com
- **License Server (Backend):** https://licenses.final-space.com

Both servers use an AES + RSA manifest system for file protection and license validation.
The project runs PHP 8.2 on host 89.44.109.166 via Explicit FTPS (port 21).
You, the GPT-5 Codex agent, are editing files live through VS Code using SFTP by liximomo.

------------------------------------------------------------
🔹 CURRENT GOAL

Stabilize the AES + manifest layer using only one protected test file until all decryption
and signature checks work perfectly.

Ignore wallet connection and purchase systems for now. Focus on making this test flow pass.

------------------------------------------------------------
🔹 ACTIVE FILES (Store: 2.final-space.com)

Wrapper (public entry): /api/fetch_products.php
Encrypted sealed file: /protected/api/fetch_products.php.aes
AES loader: /protected/sealed_loader.php
Manifest: /protected/manifest.json
Signature: /protected/manifest.sig
RSA public key: /protected/keys/server_public.pem
AES key: /protected/keys/aes_secret.key
Log file: /data/fetch_test_log.txt
Health check: /status.php

All other folders (e.g. /protected_src/) are ignored for now.

------------------------------------------------------------
🔹 ACTIVE FILES (License Server: licenses.final-space.com)

Reseal script: /admin/reseal_and_verify.php
AES helper: /inc/aes_helper.php
Config: /config.php
Output folder: /data/integrity/2.final-space.com/
Upload targets: manifest.json, manifest.sig, server_public.pem

------------------------------------------------------------
🔹 LOGIC FLOW

1️⃣ Reseal on License Server:
    php admin/reseal_and_verify.php domain=2.final-space.com license=FS-DEMO-000000
    → Creates .aes file, manifest, and signature, then uploads them to the Store.

2️⃣ Verify Manifest on Store:
    Visit https://2.final-space.com/status.php
    Should show:
      [AES] Key file found
      [Manifest] Signature: ✅ Valid

3️⃣ Test Decryption:
    Visit https://2.final-space.com/api/fetch_products.php
    Expect:
      {"ok":true,"msg":"Decrypted successfully"}

4️⃣ If Fails:
    Check /data/fetch_test_log.txt for [FS-AES] messages.
    Fix issue and reseal again.

------------------------------------------------------------
🔹 WHEN TO RESEAL

Always reseal after any change in:
/protected/sealed_loader.php
/inc/aes_helper.php
/admin/reseal_and_verify.php
/protected/manifest.json
/protected/keys/aes_secret.key

Command (same every time):
    php admin/reseal_and_verify.php domain=2.final-space.com license=FS-DEMO-000000

------------------------------------------------------------
🔹 TEST SEQUENCE

1️⃣ Run reseal → creates .aes + manifest
2️⃣ Upload manifest + keys → appear in /protected/
3️⃣ Check /status.php → ✅ AES key & signature valid
4️⃣ Test /api/fetch_products.php → ✅ JSON decrypted OK
5️⃣ Review /data/fetch_test_log.txt → “Decryption OK”

Repeat until all are green.

------------------------------------------------------------
🔹 AGENT RESPONSIBILITIES

You (GPT-5 Codex) must:
1. Scan both servers and confirm file structure.
2. Verify /protected/, /protected/keys/, and /data/ are writable.
3. Run reseal command until Store decrypts successfully.
4. Read /data/fetch_test_log.txt for [FS-AES] errors.
5. Repair path, permission, or manifest mismatches.
6. Ensure status.php and fetch_products.php return valid JSON.
7. Auto-reseal whenever AES, loader, or manifest changes.
8. Report success when wrapper decrypts with no errors.

------------------------------------------------------------
🔹 SUCCESS CRITERIA

✅ https://2.final-space.com/status.php shows valid signature.
✅ https://2.final-space.com/api/fetch_products.php decrypts successfully.
✅ /data/fetch_test_log.txt ends with “Decryption OK”.
❌ No “Missing AES secret” or “Invalid manifest” errors in logs.

When these pass, the AES protection layer is stable.

------------------------------------------------------------
🔹 NOTES

- Ignore wallet connection for now.
- Do NOT edit /protected_src/ in this phase.
- Always use FTPS (Explicit TLS, port 21) for uploads.
- Keep backups in F:\FinalSpace_Backups\

End of Instruction — AES Single-File Stabilization Phase v1.0
Prepared for GPT‑5 Codex Agent — November 2025
------------------------------------------------------------
