import React from 'react';
import CoffeeOrderForm from './CoffeeOrderForm';
import CurrentCoffeeAnimation from './CurrentCoffeeAnimation';
import MachineControls from './MachineControls';

const MainControlPanel = (props) => {
  // Destructurer les props nécessaires pour chaque sous-composant
  const {
    coffeeOrder, handleCoffeeOrderChange, handleOrderSubmit, isOrderLoading, // Pour CoffeeOrderForm
    processingOrders, handleStopProcessingOrder, cancellingOrderId, // Pour CurrentCoffeeAnimation
    machineStatus, machineStatusTranslations, handleStartMachine, handleStopMachine, handleRestartMachine, isMachineBusy, isMachineRunning, isMachineRestarting // Pour MachineControls
  } = props;

  return (
    <div className="main-control-panel component-panel">
      <CoffeeOrderForm 
        coffeeOrder={coffeeOrder} 
        handleCoffeeOrderChange={handleCoffeeOrderChange} 
        handleOrderSubmit={handleOrderSubmit} 
        isOrderLoading={isOrderLoading} 
      />
      <CurrentCoffeeAnimation 
        processingOrders={processingOrders} 
        handleStopProcessingOrder={handleStopProcessingOrder} 
        cancellingOrderId={cancellingOrderId} 
      />
      <MachineControls 
        machineStatus={machineStatus}
        machineStatusTranslations={machineStatusTranslations}
        handleStartMachine={handleStartMachine}
        handleStopMachine={handleStopMachine}
        handleRestartMachine={handleRestartMachine}
        isMachineBusy={isMachineBusy}
        isMachineRunning={isMachineRunning}
        isMachineRestarting={isMachineRestarting}
      />
    </div>
  );
};

export default MainControlPanel; 