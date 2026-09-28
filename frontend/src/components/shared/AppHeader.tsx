import Link from "next/link";
import { SupportSignOutButton } from "@/components/support/SupportSignOutButton";
import styles from "./AppHeader.module.css";

type AppHeaderProps = {
  active: "customer" | "support";
};

export function AppHeader({ active }: AppHeaderProps) {
  return (
    <header className={styles.header}>
      <Link className={styles.brand} href="/" aria-label="WORKNOON Refund Support home">
        <span className={styles.brandMark} aria-hidden="true">W</span>
        <span>WORKNOON <span className={styles.brandDivider}>/</span> REFUND SUPPORT</span>
      </Link>
      <nav className={styles.navigation} aria-label="Main navigation">
        <Link className={active === "customer" ? styles.active : undefined} href="/">
          Request a refund
        </Link>
        <Link className={active === "support" ? styles.active : undefined} href="/support">
          Support dashboard
        </Link>
        {active === "support" && <SupportSignOutButton />}
      </nav>
    </header>
  );
}
