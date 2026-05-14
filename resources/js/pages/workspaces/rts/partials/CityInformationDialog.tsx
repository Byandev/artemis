import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

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
                        {selectedCity?.hasData
                            ? '📍 City Information'
                            : 'No Data Available'}
                    </DialogTitle>
                    <DialogDescription>
                        {selectedCity && (
                            <div className="mt-3 space-y-2 text-base">
                                <p>
                                    <strong>🏙️ City:</strong>{' '}
                                    {selectedCity.city}
                                </p>
                                <p>
                                    <strong>🏛️ Province:</strong>{' '}
                                    {selectedCity.province}
                                </p>

                                {selectedCity.hasData ? (
                                    <p>
                                        <strong>📊 RTS Rate:</strong>{' '}
                                        {selectedCity.value}%
                                    </p>
                                ) : (
                                    <p className="font-medium text-red-500">
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
