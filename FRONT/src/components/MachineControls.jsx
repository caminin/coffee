import React from 'react';

const MachineControls = ({ machineStatus, machineStatusTranslations, handleStartMachine, handleStopMachine, handleRestartMachine, isMachineBusy, isMachineRunning, isMachineRestarting }) => {
  console.log('[MachineControls] Props:', { machineStatus, isMachineBusy, isMachineRunning, isMachineRestarting });
  return (
    <div className="machine-controls component-panel section-mt">
      <p className="machine-title">Contrôle de la Machine</p>
      <div className="machine-status">
        <p>État de la machine: <strong className={`status-text status-${machineStatus}`}>{machineStatusTranslations[machineStatus] || machineStatus}</strong></p>
      </div>
      <div className="machine-actions">
        <button className="machine-button" onClick={handleStartMachine} disabled={isMachineRunning}>Démarrer</button>
        <button className="machine-button" onClick={handleStopMachine} disabled={isMachineBusy || !isMachineRunning || isMachineRestarting}>Arrêter</button>
        <button className="machine-button" onClick={handleRestartMachine} disabled={isMachineBusy || isMachineRestarting}>Redémarrer</button>
      </div>
    </div>
  );
};

export default MachineControls; 