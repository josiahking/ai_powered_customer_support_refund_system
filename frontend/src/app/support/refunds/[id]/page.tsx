import { RefundDetailView } from "@/components/support/RefundDetailView";
import { SupportAuthGate } from "@/components/support/SupportAuthGate";

export default async function RefundDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return (
    <SupportAuthGate>
      <RefundDetailView requestId={id} />
    </SupportAuthGate>
  );
}
