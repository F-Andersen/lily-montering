# Security

## Repository Hygiene

Never commit real passwords, SMTP credentials, setup tokens, private keys,
administrator access files, database dumps, customer enquiries or private photos.
Use ignored environment/configuration files and protected private storage.
`.env.example` contains development-only examples, not production credentials.
Tracked SQL files are schema, migrations and initial public content only.

Operational handoff, mail, deployment and QA notes remain local and ignored.
Public documentation must not contain personal login addresses, credential-file
locations or deployment-specific access instructions.

Scan all Git history and tracked files before publication, for example using
[Gitleaks](https://github.com/gitleaks/gitleaks) with redacted output. Also inspect
test fixtures and public media for personal information: secret scanners cannot
detect every sensitive value. Do not commit scanner reports containing secrets.

An ignore rule does not remove an already tracked file or erase past commits.
If a real credential is exposed, revoke/rotate it first, then coordinate history
cleanup, forks/caches and existing clones. Do not assume deleting the current
file makes an exposed credential safe. See
[GitHub's removal procedure](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository).

## Reporting

Report security concerns privately to the repository owner. Do not include
credentials, customer data or working invitation links in public issues.
Repository hygiene checks are not a complete penetration test or a guarantee
that application dependencies and hosting are vulnerability-free.
