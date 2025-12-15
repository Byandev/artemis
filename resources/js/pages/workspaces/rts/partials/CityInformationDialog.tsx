import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogClose
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

interface CityInfo {
    city: string;
    province: string;
    value: number;
    hasData: boolean;
}

interface CityInformationDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedCity: CityInfo | null;
}

export default function CityInformationDialog({
    open,
    onOpenChange,
    selectedCity,
}: CityInformationDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <DialogTitle className="text-xl font-semibold">
                        {selectedCity?.hasData ? "📍 City Information" : "No Data Available"}
                    </DialogTitle>
                    <DialogDescription>
                        {selectedCity && (
                            <div className="mt-3 space-y-2 text-base">
                                <p><strong>🏙️ City:</strong> {selectedCity.city}</p>
                                <p><strong>🏛️ Province:</strong> {selectedCity.province}</p>

                                {selectedCity.hasData ? (
                                    <p><strong>📊 RTS Rate:</strong> {selectedCity.value}%</p>
                                ) : (
                                    <p className="text-red-500 font-medium">
                                        No available RTS data for this area.
                                    </p>
                                )}
                            </div>
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button
                            variant="secondary"
                            onClick={() => onOpenChange(false)}
                        >
                            Close
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
