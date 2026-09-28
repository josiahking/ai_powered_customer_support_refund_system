import { render, screen, waitFor } from "@testing-library/react";
import { ApiError, getSupportSession } from "@/lib/api";
import { SupportAuthGate } from "./SupportAuthGate";

const mockReplace = jest.fn();
let mockPathname = "/support";

jest.mock("next/navigation", () => ({
  usePathname: () => mockPathname,
  useRouter: () => ({ replace: mockReplace }),
}));

jest.mock("@/lib/api", () => {
  const actual = jest.requireActual<typeof import("@/lib/api")>("@/lib/api");
  return { ...actual, getSupportSession: jest.fn() };
});

const getSupportSessionMock = jest.mocked(getSupportSession);

describe("SupportAuthGate", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockPathname = "/support";
  });

  it("redirects unauthenticated visitors to login with the internal destination", async () => {
    mockPathname = "/support/refunds/31";
    getSupportSessionMock.mockRejectedValue(new ApiError("Unauthenticated.", 401));

    render(<SupportAuthGate><p>Operational data</p></SupportAuthGate>);

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith(
      "/support/login?next=%2Fsupport%2Frefunds%2F31",
    ));
    expect(screen.queryByText("Operational data")).not.toBeInTheDocument();
  });

  it("renders protected content after the session is confirmed", async () => {
    getSupportSessionMock.mockResolvedValue({ authenticated: true });

    render(<SupportAuthGate><p>Support dashboard content</p></SupportAuthGate>);

    expect(await screen.findByText("Support dashboard content")).toBeInTheDocument();
  });

  it("keeps protected content hidden when session verification is unavailable", async () => {
    getSupportSessionMock.mockRejectedValue(new ApiError("Unavailable.", 503));

    render(<SupportAuthGate><p>Operational data</p></SupportAuthGate>);

    expect(await screen.findByRole("alert")).toHaveTextContent(/could not verify support access/i);
    expect(screen.queryByText("Operational data")).not.toBeInTheDocument();
  });
});
