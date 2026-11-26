import { ProLock, Button } from '@mhm/ui-core';

/** Locked: the fallback (an upgrade prompt) stands where the Pro feature would be. */
export const Locked = () => (
	<ProLock
		unlocked={ false }
		fallback={
			<div>
				<p style={ { marginTop: 0 } }>Vendor payouts are part of Rentiva Pro.</p>
				<Button variant="primary" href="#upgrade">Upgrade to Pro</Button>
			</div>
		}
	>
		<p>Payout schedule: every Monday.</p>
	</ProLock>
);

/** Unlocked: the children render. */
export const Unlocked = () => (
	<ProLock unlocked fallback={ <p>Pro only</p> }>
		<p style={ { margin: 0 } }>Payout schedule: every Monday · next payout ₺12,450 on 30/11.</p>
	</ProLock>
);
