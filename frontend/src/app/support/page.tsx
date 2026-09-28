import { SupportDashboard } from "@/components/support/SupportDashboard";
import { SupportAuthGate } from "@/components/support/SupportAuthGate";

export default function SupportPage() {
  return (
    <SupportAuthGate>
      <SupportDashboard />
    </SupportAuthGate>
  );
}
