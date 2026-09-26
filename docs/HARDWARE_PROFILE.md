# Hardware profile for this prototype

Target machine supplied by product owner:
- Windows 11
- Intel Core i5-1035G1
- RAM 16 GB
- Radeon 620
- SATA drive D: ~200 GB free
- Samsung EVO SSD: ~20 GB free

Decisions:
1. Place project, PostgreSQL bind-mounted data, documents, backups and exports on drive D.
2. Do not use the 20 GB SSD as the main project/data location.
3. Treat GPU as unavailable for core AI acceleration; V0.0 requires no GPU.
4. Do not run a local LLM during V0.0. Later, local CPU models can be evaluated only as an offline/fallback provider.
5. Full reasoning will initially use Manual ChatGPT or OpenAI API when those milestones are activated.
