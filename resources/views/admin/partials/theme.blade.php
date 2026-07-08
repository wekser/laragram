        /* Shared admin-panel design tokens — single source of truth for the
           palette, consumed by both layout.blade.php and login.blade.php.
           Telegram Web "Day" (light) / "Night blue" (dark) inspired. */
        :root {
            --accent-rgb: 51, 144, 236;             /* single source for the accent channel */
            --bg: #f4f4f5; --panel: #ffffff; --text: #1a1a1a; --muted: #707579;
            --border: #e7e7e8; --accent: rgb(var(--accent-rgb)); --accent-hover: #2b7fd4;
            --accent-soft: rgba(var(--accent-rgb), .10); --accent-text: #ffffff;
            --ok-bg: #e6f7ea; --ok-text: #1a9c46; --off-bg: #fdeaea; --off-text: #d83b3b;
            --row-hover: #f7f8fa;
            --radius: 12px;
            --ring: 0 0 0 3px rgba(var(--accent-rgb), .20);
            --accent-glow: 0 4px 12px rgba(var(--accent-rgb), .28);
            --brand-gradient: radial-gradient(circle at 30% 25%, #5eb5f7, var(--accent));
            --shadow: 0 1px 2px rgba(0,0,0,.04), 0 6px 16px rgba(0,0,0,.03);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0e1621; --panel: #17212b; --text: #ffffff; --muted: #708499;
                --border: #242f3d; --accent-hover: #4ea3f0;
                --accent-soft: rgba(var(--accent-rgb), .18); --accent-text: #ffffff;
                --ok-bg: #14351f; --ok-text: #6ee7a0; --off-bg: #3a1517; --off-text: #f4a3a3;
                --row-hover: #1c2a38;
                --ring: 0 0 0 3px rgba(var(--accent-rgb), .30);
                --shadow: none;
            }
        }
        * { box-sizing: border-box; }
