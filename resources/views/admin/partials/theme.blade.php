        /* Shared admin-panel design tokens — single source of truth for the
           palette, consumed by both layout.blade.php and login.blade.php. */
        :root {
            --bg: #f6f7f9; --panel: #ffffff; --text: #1c2024; --muted: #6b7280;
            --border: #e5e7eb; --accent: #2563eb; --accent-text: #ffffff;
            --ok-bg: #dcfce7; --ok-text: #166534; --off-bg: #fee2e2; --off-text: #991b1b;
            --shadow: 0 1px 2px rgba(0,0,0,.06);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1115; --panel: #171a21; --text: #e6e8eb; --muted: #9aa4b2;
                --border: #262b36; --accent: #3b82f6; --accent-text: #ffffff;
                --ok-bg: #14351f; --ok-text: #86efac; --off-bg: #3a1517; --off-text: #fca5a5;
                --shadow: none;
            }
        }
        * { box-sizing: border-box; }
