import { useMemo } from 'react';
import { useCampaignSelectionStore } from '@/stores/useCampaignSelectionStore';

export const useCampaignFilter = () => {
    const { selections } = useCampaignSelectionStore();
    const campaignSelections: Record<string, boolean> = selections['campaigns'] || {};

    // Memoize selected campaign IDs to avoid recalculation on every render
    const selectedCampaignIds = useMemo(() => {
        return Object.keys(campaignSelections).filter(id => campaignSelections[id]);
    }, [campaignSelections]);

    // Memoize encoded campaign IDs string
    const encodedCampaignIds = useMemo(() => {
        return selectedCampaignIds.length > 0 ? encodeURIComponent(JSON.stringify(selectedCampaignIds)) : undefined;
    }, [selectedCampaignIds]);

    return {
        selectedCampaignIds,
        encodedCampaignIds,
        hasCampaignFilter: selectedCampaignIds.length > 0,
    };
};
